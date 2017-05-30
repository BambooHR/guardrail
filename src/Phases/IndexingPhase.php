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
	 * @return int
	 */
	public function index(Config $config, OutputInterface $output, \RecursiveIteratorIterator $it2, $stubs = false) {
		$baseDir = $config->getBasePath();
		$symbolTable = $config->getSymbolTable();
		$indexer = new SymbolTableIndexer($symbolTable, $output);
		$traverser1 = new NodeTraverser;
		$traverser1->addVisitor(new NameResolver());
		$traverser2 = new NodeTraverser;
		$traverser2->addVisitor($indexer);
		$parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);

		$configArr = $config->getConfigArray();

		$count = 0;
		foreach ($it2 as $file) {
			if (($file->getExtension() == "php" || $file->getExtension() == "inc") && $file->isFile()) {
				$name = Util::removeInitialPath($baseDir, $file->getPathname());
				if (strpos($name, "phar://") === 0) {
					$name = str_replace(Phar::running(), "", $name );
					while ($name[0] == '/') {
						$name = substr($name, 1);
					}
					$name = "phar://" . $name;
				}
				try {
					if (!$stubs && isset($configArr['ignore']) && is_array($configArr['ignore']) && Util::matchesGlobs($baseDir, $file->getPathname(), $configArr['ignore'])) {
						continue;
					}
					++$count;
					$output->output(".", " - $count:" . $name);

					// If the $fileName is in our phar then make it a relative path so that files that we index don't
					// depend on the phar file existing in a particular directory.
					$fileData = file_get_contents($file->getPathname());
					if ($config->shouldReindex()) {
						$symbolTable->removeFileFromIndex($file->getPathname());
					}

					$indexer->setFilename($name);
					$statements = $parser->parse($fileData);
					if ($statements) {
						$traverser1->traverse($statements);
						$traverser2->traverse($statements);
					}
				} catch (Error $exception) {
					$output->emitError(__CLASS__, $file, 0, ' Parse Error: ' . $exception->getMessage() . "\n" );
				}
			}
		}
		return $count;
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
		foreach ($indexPaths as $path) {
			$tmpDirectory = Util::fullDirectoryPath($baseDirectory, $path);
			$output->outputVerbose("\n\nIndexing Directory: " . $tmpDirectory . "\n");
			$it = new \RecursiveDirectoryIterator($tmpDirectory, \FilesystemIterator::SKIP_DOTS);
			$it2 = new \RecursiveIteratorIterator($it);
			$this->index($config, $output, $it2);
		}

		// If Guardrail is in vendor and you index vendor (which you should) then it won't need to
		// re-index the extra stubs.  If guardrail is outside of vendor then we need to make sure
		// we index the extra stubs.
		if (!$config->getSymbolTable()->isDefinedClass('closure') ) {
			$it = new \RecursiveDirectoryIterator(dirname(__DIR__) . "/ExtraStubs");
			$it2 = new \RecursiveIteratorIterator($it);
			$this->index($config, $output, $it2, true);
		}

		$this->indexTraitClasses($config->getSymbolTable(), $output);
	}
}
