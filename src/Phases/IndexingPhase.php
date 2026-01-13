<?php

/**
 * Guardrail.  Copyright (c) 2016-2024, BambooHR
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\Phases;

use BambooHR\Guardrail\Config;
use BambooHR\Guardrail\DirectoryLister;
use BambooHR\Guardrail\NodeVisitors\DocBlockNameResolver;
use BambooHR\Guardrail\NodeVisitors\PromotedPropertyVisitor;
use BambooHR\Guardrail\NodeVisitors\SymbolTableIndexer;
use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\Phases\Processes\Child\IndexChildProcess;
use BambooHR\Guardrail\Phases\Processes\Child\TraitIndexChildProcess;
use BambooHR\Guardrail\Phases\Processes\Parent\AnalyzingParentProcess;
use BambooHR\Guardrail\Phases\Processes\Parent\IndexParentProcess;
use BambooHR\Guardrail\Phases\Processes\Parent\ProcessManager;
use BambooHR\Guardrail\Phases\Processes\Parent\TraitIndexingParent;
use BambooHR\Guardrail\Socket;
use BambooHR\Guardrail\SymbolTable\PersistantSymbolTable;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\Util;
use Phar;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use Throwable;

/**
 * Class IndexingPhase
 *
 * @package BambooHR\Guardrail\Phases
 */
class IndexingPhase {

	private IndexParentProcess $processManager;

	private $parser = null;
	private $traverser1 = null;
	private $traverser2 = null;
	private $indexer = null;

	/**
	 * IndexingPhase constructor.
	 * @param Config $config -
	 */
	function __construct(private Config $config, OutputInterface $output) {
		$this->processManager = new IndexParentProcess($config, $output);
		$this->traverser1 = new NodeTraverser;
		$this->traverser1->addVisitor($resolver = new NameResolver());
		$this->traverser1->addVisitor(new DocBlockNameResolver($resolver->getNameContext()));
		$this->traverser1->addVisitor(new PromotedPropertyVisitor());
		$this->traverser2 = new NodeTraverser;
		$this->indexer = new SymbolTableIndexer($config->getSymbolTable(), $output);
		$this->traverser2->addVisitor($this->indexer);
		$this->parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
	}

	/**
	 * Generator function that yields the next file to scan.
	 *
	 * @param \RecursiveIteratorIterator $it2   Instance of RecursiveIteratorIterator
	 * @param bool                       $stubs Check the stubs
	 *
	 * @return \Generator
	 */
	private function getFileList(array $paths) {
		$baseDir = $this->config->getBasePath();
		$configArr = $this->config->getConfigArray();

		foreach ($paths as $path) {
			$tmpDirectory = Util::fullDirectoryPath($baseDir, $path);
			$generator = DirectoryLister::getGenerator($tmpDirectory);
			foreach ($generator as $filePath) {
				if (preg_match('/\\.(php|inc)$/', $filePath) && is_file($filePath)) {
					if (isset($configArr['ignore']) && is_array($configArr['ignore']) && Util::matchesGlobs($baseDir, $filePath, $configArr['ignore'])) {
						continue;
					}
					yield $filePath;
				}
			}
		}
	}

	/**
	 * @param string $pathName -
	 * @return int The length in bytes of the file that was indexed.
	 * @guardrail-ignore Standard.Exception.Base
	 */
	function indexFile($pathName) {
		$baseDir = $this->config->getBasePath();
		$name = Util::removeInitialPath($baseDir, $pathName);
		// If the $fileName is in our phar then make it a relative path so that files that we index don't
		// depend on the phar file existing in a particular directory.
		if (strpos($name, "phar://") === 0) {
			$name = str_replace(Phar::running(), "", $name );
			while ($name[0] == '/') {
				$name = substr($name, 1);
			}
			$name = "phar://" . $name;
		}

		if ($this->config->shouldReindex()) {
			$this->config->getSymbolTable()->removeFileFromIndex($pathName);
		}

		$fileData = file_get_contents($pathName);

		return $this->indexString($name, $fileData);
	}


	/**
	 * @param Config          $config The config
	 * @param OutputInterface $output Output
	 * @param \Iterator       $itr    A generator function that yields filenames to scan.
	 * @return void
	 */
	function indexList(Config $config, OutputInterface $output, $itr) {
		$table = $config->getSymbolTable();
		if ($table instanceof PersistantSymbolTable) {
			//$table->disconnect();
		}

		$this->processManager->init($itr);

		// Fire up our child processes and give them each a file to index.
		for ($fileNumber = 0; $fileNumber < $config->getProcessCount() && $itr->valid(); ++$fileNumber, $itr->next()) {
			$processNumber = $fileNumber;
			$child = $this->processManager->createChild(new IndexChildProcess($processNumber, $config->getSymbolTable(), $this));

			Socket::writeComplete($child, "INDEX " . $itr->current() . "\n");

			if (!$output->isTTY() && $config->getOutputLevel() == 1) {
				$output->outputVerbose(".");
			}
			if ($config->getOutputLevel() == 2) {
				$output->outputExtraVerbose( sprintf("%d - %s\n", $fileNumber, $itr->current()) );
			}
		}
		$this->processManager->loopWhileConnections();
		$this->processManager->displayStatusUpdate();
		$output->outputVerbose("\n");
	}

	/**
	 * run
	 *
	 * @param Config          $config Instance of config
	 * @param OutputInterface $output Instance of OutputInterface
	 *
	 * @return void
	 */
	public function run(Config $config, OutputInterface $output) {
		$configArr = $config->getConfigArray();
		$baseDirectory = $config->getBasePath();
		$indexPaths = $configArr['index'];
		if (! Util::configDirectoriesAreValid($baseDirectory, $indexPaths)) {
			$output->output("Invalid or missing paths in your index config section.",
				"Invalid or missing paths in your index config section.");
			exit;
		}
		$output->outputVerbose("Index directories are valid: Indexing starting.\n");

		$this->indexList($config, $output, $this->getFileList($indexPaths) );

		$output->outputVerbose("Merging indexes\n");
		$table = $config->getSymbolTable();
		if ($table instanceof PersistantSymbolTable) {
			$table->connect(0);
			$table->indexTable($config->getProcessCount());
		}

		$this->indexTraitClasses($table, $output);
	}

	function indexTraitClasses(SymbolTable $table, OutputInterface $output): void {
		if ($table instanceof PersistantSymbolTable) {
			$table->connect(0);
		}
		$classes = $table->getClassesThatUseAnyTrait();
		$manager = new TraitIndexingParent($classes, $this->config, $table, $output);
		$manager->run();
		if ($table instanceof PersistantSymbolTable) {
			$table->disconnect();
		}
	}

	/**
	 * @param bool|string $name
	 * @param bool|string $fileData
	 * @param string      $pathName
	 * @return int
	 */
	public function indexString(string $name, string $fileData): int {
		$this->indexer->setFilename($name);
		try {
			$statements = $this->parser->parse($fileData);
			if ($statements) {
				$this->traverser1->traverse($statements);
				$this->traverser2->traverse($statements);
			}
		} catch (Throwable $exc) {
			echo "\n[$name] ERROR " . $exc->getMessage() . "\n";
		}
		return strlen($fileData);
	}
}
