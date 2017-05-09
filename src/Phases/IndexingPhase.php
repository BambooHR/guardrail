<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\Phases;

use BambooHR\Guardrail\Abstractions\Class_;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TraitImporter;
use PhpParser\Error;
use PhpParser\ParserFactory;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeTraverser;
use BambooHR\Guardrail\NodeVisitors\SymbolTableIndexer;
use BambooHR\Guardrail\Util;
use BambooHR\Guardrail\Config;
use BambooHR\Guardrail\Output\OutputInterface;


class IndexingPhase
{

	function index(Config $config, OutputInterface $output, \RecursiveIteratorIterator $it2, $stubs = false) {
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
			if (($file->getExtension() == "php" || $file->getExtension() =="inc") && $file->isFile()) {
				$name = Util::removeInitialPath($baseDir, $file->getPathname());
				if(strpos($name,"phar://")===0) {
					$name = str_replace( \Phar::running(), "", $name );
					while($name[0]=='/') {
						$name=substr($name,1);
					}
					$name="phar://".$name;
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
					if($config->shouldReindex()) {
						$symbolTable->removeFileFromIndex($file->getPathname());
					}

					$indexer->setFilename($name);
					$stmts = $parser->parse($fileData);
					if ($stmts) {
						$traverser1->traverse($stmts);
						$traverser2->traverse($stmts);
					}
				} catch (Error $e) {
					$output->emitError(__CLASS__, $file, 0,' Parse Error: ' . $e->getMessage() . "\n" );
				}
			}
		}
		return $count;
	}

	function indexTraitClasses(SymbolTable $symbolTable, OutputInterface $output) {
		$output->outputVerbose("Importing traits\n");
		$count = 0;
		foreach($symbolTable->getClassesThatUseATrait() as $className) {
			$class = $symbolTable->getClass($className);
			$symbolTable->updateClass( $class );
			$classAb = new Class_($class);
			$output->output(".", " - (++$count): ".$className);
		}
	}

	function run(Config $config, OutputInterface $output) {
		$configArr = $config->getConfigArray();
		$indexPaths = $configArr['index'];

		foreach ($indexPaths as $directory) {
			$tmpDirectory = strpos($directory, "/") === 0 ? $directory : $config->getBasePath() . "/" . $directory;
			$output->outputVerbose("Indexing Directory: " . $tmpDirectory . "\n");
			$it = new \RecursiveDirectoryIterator($tmpDirectory, \FilesystemIterator::SKIP_DOTS);
			$it2 = new \RecursiveIteratorIterator($it);
			$this->index($config, $output, $it2);
		}

		$it = new \RecursiveDirectoryIterator(dirname(__DIR__) . "/ExtraStubs");
		$it2 = new \RecursiveIteratorIterator($it);
		$this->index($config, $output, $it2, true);

		$this->indexTraitClasses($config->getSymbolTable(), $output);
/*

		$it = new \RecursiveDirectoryIterator(dirname(dirname(__DIR__)) . "/vendor/phpstubs/phpstubs/res");
		$it2 = new \RecursiveIteratorIterator($it);
		$this->index($config, $it2, true);
*/
	}
}
