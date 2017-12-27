<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\Phases;

use BambooHR\Guardrail\ProcessManager;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use Phar;
use PhpParser\Error;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeTraverser;
use BambooHR\Guardrail\NodeVisitors\SymbolTableIndexer;
use BambooHR\Guardrail\Util;
use BambooHR\Guardrail\Config;
use BambooHR\Guardrail\Output\OutputInterface;

/**
 * Class IndexingPhase
 *
 * @package BambooHR\Guardrail\Phases
 */
class IndexingPhase {

	private $processManager;

	function __construct() {
		$this->processManager = new ProcessManager();
	}

	/**
	 * Generator function that yields the next file to scan.
	 *
	 * @param Config                     $config Instance of Config
	 * @param \RecursiveIteratorIterator $it2    Instance of RecursiveIteratorIterator
	 * @param bool                       $stubs  Check the stubs
	 *
	 * @return \Generator
	 */
	private function getFileList(Config $config, \RecursiveIteratorIterator $it2, $stubs = false) {
		$baseDir = $config->getBasePath();
		$configArr = $config->getConfigArray();
		foreach ($it2 as $file) {
			if (($file->getExtension() == "php" || $file->getExtension() == "inc") && $file->isFile()) {
				if (!$stubs && isset($configArr['ignore']) && is_array($configArr['ignore']) && Util::matchesGlobs($baseDir, $file->getPathname(), $configArr['ignore'])) {
					continue;
				}
				yield $file->getPathname();
			}
		}
	}

	/**
	 * @param Config $config   -
	 * @param string $pathName -
	 * @return int The length in bytes of the file that was indexed.
	 */
	function indexFile(Config $config, $parser, $indexer, $traverser1, $traverser2, $pathName) {
		$baseDir = $config->getBasePath();
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

		if ($config->shouldReindex()) {
			$config->getSymbolTable()->removeFileFromIndex($pathName);
		}

		$fileData = file_get_contents($pathName);

		$indexer->setFilename($name);
		//$t1=microtime(true);
		try {
			$statements = $parser->parse($fileData);
			//$t2=microtime(true);
			if ($statements) {
				$traverser1->traverse($statements);
				//$t3=microtime(true);
				$traverser2->traverse($statements);
				//$t4=microtime(true);
				//printf("Parse: %.3f, T1: %.3f, T2: %.3f\n", $t2-$t1, $t3-$t2, $t4-$t3);
			}
		} catch (\Exception $exc) {
			echo "ERROR " . $exc->getMessage() . "\n";
		}
		return strlen($fileData);
	}

	/**
	 * indexTraitClasses
	 *
	 * @param SymbolTable     $symbolTable Instance of the SymbolTable
	 * @param OutputInterface $output      Instance of the OutputInterface
	 *
	 * @return void
	 */
	public function indexTraitClasses(SymbolTable $symbolTable, OutputInterface $output) {
		$output->outputVerbose("\n\nImporting traits\n");
		$count = 0;
		foreach ($symbolTable->getClassesThatUseAnyTrait() as $className) {
			$class = $symbolTable->getClass($className);
			$symbolTable->updateClass( $class );
			$output->output(".", " - " . (++$count) . ": " . $className);
		}
	}


	/**
	 * @param Config $config 0
	 * @return resource The client socket that the server should communicate with.
	 */
	function createIndexingChild(Config $config) {
		$indexer = new SymbolTableIndexer($config->getSymbolTable());
		$traverser1 = new NodeTraverser;
		$traverser1->addVisitor(new NameResolver());
		$traverser2 = new NodeTraverser;
		$traverser2->addVisitor($indexer);
		$parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);

		return $this->processManager->createChild(
			// This closure represents the child process.  The value it returns
			// will be the exit code of the child process.
			function($socket) use($config, $parser, $indexer, $traverser1, $traverser2) {
				$config->getSymbolTable()->connect();
				while (1) {
					$receive = trim(socket_read($socket, 200, PHP_NORMAL_READ));
					if ($receive == "DONE") {
						$config->getSymbolTable()->flushInserts();
						return 0;
					} else {
						list(, $file) = explode(' ', trim($receive));
						$size = $this->indexFile($config, $parser, $indexer, $traverser1, $traverser2, $file);
						socket_write($socket, "INDEXED $size $file\n");
					}
				}
			}
		);
	}

	/**
	 * @param Config          $config The config
	 * @param OutputInterface $output Output
	 * @param string[]        $list   The files to add
	 * @return void
	 */
	function indexList(Config $config, OutputInterface $output, \Generator $it) {
		$config->getSymbolTable()->disconnect();

		$start = microtime(true);
		$bytes = 0.0;
		// Fire up our child processes and give them each a file to index.
		for ($fileNumber = 0; $fileNumber < $config->getProcessCount() && $it->valid(); ++$fileNumber, $it->next()) {
			$child = $this->createIndexingChild($config);
			socket_write($child, "INDEX ".$it->current()."\n");
			$output->output(".", sprintf("%d - %s", $fileNumber, $it->current()));
		}

		$this->processManager->loopWhileConnections(
			function ($socket, $msg) use (&$it, &$fileNumber, &$bytes, $output, $start) {
				list($message, $details) = explode(' ', $msg, 2);

				//echo "RECEIVED:$msg from index: $index\n";
				if ($message == 'INDEXED') {
					if ($it->valid()) {
						list($size, $name) = explode(' ', $details);
						$bytes += $size;
						$output->output(".", sprintf("%d - %s", ++$fileNumber, $it->current()));
						socket_write($socket, "INDEX " . $it->current(). "\n");
						$it->next();
					} else {
						socket_write($socket, "DONE\n");
						return ProcessManager::CLOSE_CONNECTION;
					}
					if ($fileNumber % 50 == 0) {
						$output->output("", sprintf("Processing %.1f KB/second", $bytes / 1024 / (microtime(true) - $start)));
					}
				} else {
					$output->outputVerbose($message . " D:" . $details . "\n");
				}
				return ProcessManager::READ_CONNECTION;
			}
		);

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
			$output->output("Invalid or missing paths in your index config section.\n", "Invalid or missing paths in your index config section.\n");
			exit;
		}
		$output->outputVerbose("\nIndex directories are valid: Indexing starting\n");

		foreach ($indexPaths as $path) {
			$tmpDirectory = Util::fullDirectoryPath($baseDirectory, $path);
			$output->outputVerbose("Indexing Directory: " . $tmpDirectory . "\n");
			$it = new \RecursiveDirectoryIterator($tmpDirectory, \FilesystemIterator::SKIP_DOTS);
			$it2 = new \RecursiveIteratorIterator($it);
			$this->indexList($config, $output, $this->getFileList($config, $it2) );
		}

		// If Guardrail is in vendor and you index vendor (which you should) then it won't need to
		// re-index the extra stubs.  If guardrail is outside of vendor then we need to make sure
		// we index the extra stubs.
		$it = new \RecursiveDirectoryIterator(dirname(__DIR__) . "/ExtraStubs");
		$it2 = new \RecursiveIteratorIterator($it);
		$this->indexList($config, $output, $this->getFileList($config, $it2, true) );

		$st = $config->getSymbolTable();
		$st->connect();
		$st->indexTable();
		$table = $config->getSymbolTable();
		$this->indexTraitClasses($table, $output);
	}
}
