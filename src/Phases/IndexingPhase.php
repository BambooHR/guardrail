<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\Phases;

use BambooHR\Guardrail\DirectoryLister;
use BambooHR\Guardrail\NodeVisitors\DocBlockNameResolver;
use BambooHR\Guardrail\NodeVisitors\PromotedPropertyVisitor;
use BambooHR\Guardrail\ProcessManager;
use BambooHR\Guardrail\SocketBuffer;
use BambooHR\Guardrail\SymbolTable\PersistantSymbolTable;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use Phar;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;
use BambooHR\Guardrail\NodeVisitors\SymbolTableIndexer;
use BambooHR\Guardrail\Util;
use BambooHR\Guardrail\Config;
use BambooHR\Guardrail\Output\OutputInterface;
use Throwable;

/**
 * Class IndexingPhase
 *
 * @package BambooHR\Guardrail\Phases
 */
class IndexingPhase {

	private $processManager;

	private $parser = null;
	private $traverser1 = null;
	private $traverser2 = null;
	private $indexer = null;

	/**
	 * IndexingPhase constructor.
	 * @param Config $config -
	 */
	function __construct(Config $config) {
		$this->processManager = new ProcessManager();
		$this->traverser1 = new NodeTraverser;
		$this->traverser1->addVisitor($resolver = new NameResolver());
		$this->traverser1->addVisitor(new DocBlockNameResolver($resolver->getNameContext()));
		$this->traverser1->addVisitor(new PromotedPropertyVisitor());
		$this->traverser2 = new NodeTraverser;
		$this->indexer = new SymbolTableIndexer($config->getSymbolTable());
		$this->traverser2->addVisitor($this->indexer);
		//if (PhpAstParser::isSupported()) {
		//	$this->parser = new PhpAstParser();
		//} else {
			$this->parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
		//}
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
	private function getFileList(Config $config, array $paths, $stubs = false) {
		$baseDir = $config->getBasePath();
		$configArr = $config->getConfigArray();

		foreach($paths as $path) {
			$tmpDirectory = Util::fullDirectoryPath($baseDir, $path);
			$generator = DirectoryLister::getGenerator($tmpDirectory);
			foreach ($generator as $filePath) {
				if (preg_match('/\\.(php|inc)$/', $filePath) && is_file($filePath)) {
					if (!$stubs && isset($configArr['ignore']) && is_array($configArr['ignore']) && Util::matchesGlobs($baseDir, $filePath, $configArr['ignore'])) {
						continue;
					}
					yield $filePath;
				}
			}
		}
	}

	/**
	 * @param Config $config   -
	 * @param string $pathName -
	 * @return int The length in bytes of the file that was indexed.
	 * @guardrail-ignore Standard.Exception.Base
	 */
	function indexFile(Config $config, $pathName) {
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

		return $this->indexString($name, $fileData);
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
		$symbolTable->begin();
		foreach ($symbolTable->getClassesThatUseAnyTrait() as $className) {
			$class = $symbolTable->getClass($className);
			$symbolTable->updateClass( $class );
		}
		$symbolTable->commit();
	}


	/**
	 * @param int    $processNumber -
	 * @param Config $config     -
	 * @return resource The client socket that the server should communicate with.
	 */
	function createIndexingChild($processNumber, Config $config) {
		return $this->processManager->createChild(
			// This closure represents the child process.  The value it returns
			// will be the exit code of the child process.
			function($socket) use($config, $processNumber) {
				$table = $config->getSymbolTable();
				if ($table instanceof PersistantSymbolTable) {
					$table->connect($processNumber + 1 );
				}
				$buffer = new SocketBuffer();
				while (1) {
					$buffer->read($socket);
					foreach ($buffer->getMessages() as $receive) {
						if ($receive == "DONE") {
							if ($table instanceof PersistantSymbolTable) {
								$table->flushInserts();
								$table->disconnect();
							}
							return 0;
						} else {
							list(, $file) = explode(' ', trim($receive));
							$size = $this->indexFile($config, $file);
							socket_write($socket, "INDEXED $size $file ".($processNumber+1)."\n");
						}
					}
				}
			}
		);
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

		$start = microtime(true);
		$bytes = 0.0;
		// Fire up our child processes and give them each a file to index.
		for ($fileNumber = 0; $fileNumber < $config->getProcessCount() && $itr->valid(); ++$fileNumber, $itr->next()) {
			$processNumber = $fileNumber;
			$child = $this->createIndexingChild($processNumber, $config);
			socket_write($child, "INDEX " . $itr->current() . "\n");

			if (!$output->isTTY() && $config->getOutputLevel()==1) {
				$output->outputVerbose(".");
			}
			if ($config->getOutputLevel()==2) {
				$output->outputExtraVerbose( sprintf("%d - %s\n", $fileNumber, $itr->current()) );
			}

		}

		$this->processManager->loopWhileConnections(
			function ($socket, $msg) use (&$itr, &$fileNumber, &$bytes, $output, $start, $config) {
				if ($msg === false) {
					echo "Error: Unexpected error reading from socket\n";
					return ProcessManager::CLOSE_CONNECTION;
				}
				list($message, $details) = explode(' ', $msg, 2);

				if ($message == 'INDEXED') {
					[$size, $fileName, $childProcessNumber] = explode(' ', $details);
					$bytes += $size;
					$output->outputExtraVerbose(sprintf("%d - %s ($childProcessNumber)\n", ++$fileNumber, $fileName));

					if ($itr->valid()) {
						socket_write($socket, "INDEX " . $itr->current() ."\n");
						$itr->next();
					} else {
						socket_write($socket, "DONE\n");
						return ProcessManager::CLOSE_CONNECTION;
					}
					if ($fileNumber % 50 == 0) {
						$process= sprintf(
							"Processing %s%.1f%s KB/second",
							$output->ttyContent("\33[97m"),
							$bytes / 1024 / (microtime(true) - $start),
							$output->ttyContent("\33[0m")
						);
						if ($config->getOutputLevel()==1) {
							if (!$output->isTTY()) {
								$output->outputVerbose(".");
							} else {
								$output->outputVerbose($process."   \r");
							}
						} else {
							if ($config->getOutputLevel()==2) {
								$output->outputExtraVerbose("\n".$process . "\n");
							}
						}
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
			$output->output("Invalid or missing paths in your index config section.",
				"Invalid or missing paths in your index config section.");
			exit;
		}
		$output->outputVerbose("Index directories are valid: Indexing starting.\n");

		$this->indexList($config, $output, $this->getFileList($config, $indexPaths) );

		$table = $config->getSymbolTable();
		if ($table instanceof PersistantSymbolTable) {
			$table->connect(0);
			$table->indexTable($config->getProcessCount());
		}
		$table->connect(0);
		$this->indexTraitClasses($table, $output);
		$table->disconnect();
	}

	/**
	 * @param bool|string $name
	 * @param bool|string $fileData
	 * @param string $pathName
	 * @return int
	 */
	public function indexString(string $name, string $fileData): int
	{
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
