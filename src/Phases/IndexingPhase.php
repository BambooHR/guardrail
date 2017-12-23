<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\Phases;

use BambooHR\Guardrail\SymbolTable\SymbolTable;
use Phar;
use PhpParser\Error;
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

	/**
	 * index
	 *
	 * @param Config                     $config Instance of Config
	 * @param OutputInterface            $output Instance of OutputInterface
	 * @param \RecursiveIteratorIterator $it2    Instance of RecursiveIteratorIterator
	 * @param bool                       $stubs  Check the stubs
	 *
	 * @return array
	 */
	private function getFileList(Config $config, OutputInterface $output, \RecursiveIteratorIterator $it2, $stubs = false) {
		$baseDir = $config->getBasePath();
		$configArr = $config->getConfigArray();
		$toIndex = [];
		foreach ($it2 as $file) {
			if (($file->getExtension() == "php" || $file->getExtension() == "inc") && $file->isFile()) {
				try {
					if (!$stubs && isset($configArr['ignore']) && is_array($configArr['ignore']) && Util::matchesGlobs($baseDir, $file->getPathname(), $configArr['ignore'])) {
						continue;
					}
					$toIndex[]=$file->getPathname();
				} catch (Error $exception) {
					$output->emitError(__CLASS__, $file, 0, ' Parse Error: ' . $exception->getMessage() . "\n" );
				}
			}
		}
		return $toIndex;
	}

	/**
	 * @param Config          $config   -
	 * @param OutputInterface $output   -
	 * @param string          $pathName -
	 */
	function indexFile(Config $config, $pathName) {
		$symbolTable = $config->getSymbolTable();
		$indexer = new SymbolTableIndexer($symbolTable);
		$traverser1 = new NodeTraverser;
		$traverser1->addVisitor(new NameResolver());
		$traverser2 = new NodeTraverser;
		$traverser2->addVisitor($indexer);
		$parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);

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
			$symbolTable->removeFileFromIndex($pathName);
		}

		$fileData = file_get_contents($pathName);

		$indexer->setFilename($name);
		try {
			$statements = $parser->parse($fileData);
			if ($statements) {
				$traverser1->traverse($statements);
				$traverser2->traverse($statements);
			}
		} catch(\Exception $e) {
			echo "ERROR ".$e->getMessage()."\n";
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

	function createIndexingChild(Config $config) {

		$pair = [];
		if (!socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $pair)) {
			echo "socket_create_pair failed. Reason: " . socket_strerror(socket_last_error())."\n";
		}
		$pid = pcntl_fork();
		if ($pid == -1) {
			// error
		} else if ($pid) {
			socket_close($pair[0]);
			return $pair[1];
		} else {
			$config->getSymbolTable()->connect();
			// Child process, scan each file until we receive a "DONE".
			socket_close($pair[1]);
			while (1) {
				$receive = trim(socket_read($pair[0], 200, PHP_NORMAL_READ));
				if ($receive == "DONE") {
					socket_close($pair[0]);
					exit(0);
				} else {
					list(, $file) = explode(' ', trim($receive));
					$size = $this->indexFile($config, $file);
					socket_write($pair[0], "INDEXED $size $file\n");
				}
			}
		}
	}

	/**
	 * @param Config          $config The config
	 * @param OutputInterface $output Output
	 * @param string[]        $list   The files to add
	 */
	function indexList(Config $config, OutputInterface $output, $list) {
		$config->getSymbolTable()->disconnect();

		$connections = [];
		reset($list);

		$start=microtime(true);
		$bytes = 0.0;
		// Fire up our child processes and give them each a file to index.
		for ($i = 0; $i < $config->getProcessCount(); ++$i) {
			$connection = $this->createIndexingChild($config);
			$filename = $list[$i];
			socket_write($connection, "INDEX $filename\n");
			$output->output(".", sprintf("%d - %s", $i, $list[$i]));
			$connections[] = $connection;
		}

		// Then just keep reading their responses and feeding them new files.
		while (count($connections)>0) {
			$read = $errors = $connections;
			$none = null;
			if (socket_select($read, $none, $none, null)) {
				foreach ($read as $index=>$socket) {
					$msg = trim(socket_read($socket, 200, PHP_NORMAL_READ));
					list($message, $details) = explode(' ', $msg, 2);

					//echo "RECEIVED:$msg from index: $index\n";
					if ($message == 'INDEXED') {
						if ($i < count($list)) {
							list($size, $name) = explode(' ', $details);
							$bytes += $size;
							$output->output(".", sprintf("%d - %s", $i, $list[$i]));
							if ($i % 50 == 0) {
								$estimate = (count($list) - $i) * (microtime(true) - $start) / $i;
								$output->output("", sprintf(" %.1f%% complete. %.1f seconds remaining, %.1f KB/second", $i / count($list) * 100, $estimate, $bytes / 1024 / (microtime(true) - $start)));
							}
							socket_write($socket, "INDEX " . $list[$i++] . "\n");
						} else {
							socket_write($socket,"DONE\n");
							$status = 0;
							unset($connections[$index]);
							pcntl_wait($status);
						}
					} else {
						$output->outputVerbose($message . " D:" . $details . "\n");
					}
				}
			}
		}
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
		$output->outputVerbose("\nIndex directories are valid: Indexing starting");
		$toIndex = [];
		foreach ($indexPaths as $path) {
			$tmpDirectory = Util::fullDirectoryPath($baseDirectory, $path);
			$output->outputVerbose("\n\nIndexing Directory: " . $tmpDirectory . "\n");
			$it = new \RecursiveDirectoryIterator($tmpDirectory, \FilesystemIterator::SKIP_DOTS);
			$it2 = new \RecursiveIteratorIterator($it);
			$toIndex = array_merge( $toIndex, $this->getFileList($config, $output, $it2));
		}

		// If Guardrail is in vendor and you index vendor (which you should) then it won't need to
		// re-index the extra stubs.  If guardrail is outside of vendor then we need to make sure
		// we index the extra stubs.
		$it = new \RecursiveDirectoryIterator(dirname(__DIR__) . "/ExtraStubs");
		$it2 = new \RecursiveIteratorIterator($it);
		$toIndex = array_merge( $toIndex, $this->getFileList($config, $output, $it2, true));
		$this->indexList($config, $output, $toIndex);
		$table = $config->getSymbolTable();
		$table->connect();
		$this->indexTraitClasses($table, $output);
	}
}
