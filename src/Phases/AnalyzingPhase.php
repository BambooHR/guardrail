<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\Phases;

use BambooHR\Guardrail\NodeVisitors\DocBlockNameResolver;
use BambooHR\Guardrail\Output\XUnitOutput;
use PhpParser\Error;
use PhpParser\ParserFactory;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeTraverser;
use BambooHR\Guardrail\Config;
use BambooHR\Guardrail\NodeVisitors\TraitImportingVisitor;
use BambooHR\Guardrail\Util;
use BambooHR\Guardrail\NodeVisitors\StaticAnalyzer;
use BambooHR\Guardrail\Output\OutputInterface;


class AnalyzingPhase
{
	function getPhase2Files(Config $config, OutputInterface $output, \RecursiveIteratorIterator $it2, &$toProcess) {
		$configArr=$config->getConfigArray();
		foreach ($it2 as $file) {
			if ($file->getExtension() == "php" && $file->isFile()) {
				if (isset($configArr['test-ignore']) && is_array($configArr['test-ignore']) && Util::matchesGlobs($config->getBasePath(), $file->getPathname(), $configArr['test-ignore'])) {
					continue;
				}
				$toProcess[] = $file->getPathname();
			}
		}
	}

	function phase2(Config $config, OutputInterface $output, $toProcess) {

		$traverser1 = new NodeTraverser;
		$traverser1->addVisitor(new DocBlockNameResolver());
		$analyzer = new StaticAnalyzer($config->getBasePath(), $config->getSymbolTable(), $output, $config);

		$traverser2 = new NodeTraverser();
		$traverser2->addVisitor(new TraitImportingVisitor($config->getSymbolTable()));

		$traverser3 = new NodeTraverser;
		$traverser3->addVisitor($analyzer);

		$parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
		$processingCount = 0;
		foreach ($toProcess as $file) {
			try {
				$name = Util::removeInitialPath($config->getBasePath(), $file);
			 	$output->output(".", $name);
				$processingCount++;
				//echo " - $processingCount:" . $file . "\n";
				$fileData = file_get_contents($file);
				$stmts = $parser->parse($fileData);
				if ($stmts) {
					$analyzer->setFile($name);
					$stmts=$traverser1->traverse($stmts);
					$stmts=$traverser2->traverse($stmts);
					$traverser3->traverse($stmts);
				}
			} catch (Error $e) {
				$output->emitError( __CLASS__, $file, 0, "Parse error", $e->getMessage() );
			} catch(\BambooHR\Guardrail\Exceptions\UnknownTraitException $e) {
				$output->emitError( __CLASS__, $file, 0, "Unknown trait error", $e->getMessage() );
			}

		}
		if($output instanceof XUnitOutput) {
		//	print_r($output->getCounts());
		}
		return ($output->getErrorCount()>0 ? 1 : 0);
	}

	function getMultipartFileName(Config $config, $part) {
		$outputFileName=$config->getOutputFile();
		$lastPart = strrpos($outputFileName,".");
		if($lastPart>0) {
			$outputFileName=substr($outputFileName,0, $lastPart+1).$part.".xml";
		} else {
			$outputFileName=$outputFileName.$part;
		}
		return $outputFileName;
	}

	function runChildProcesses(Config $config, OutputInterface $output, array $toProcess) {
		$error=false;
		$files = [];
		$groupSize = intval(count($toProcess) / $config->getProcessCount());
		for ($i = 0; $i < $config->getProcessCount(); ++$i) {
			$group = ($i == $config->getProcessCount() -1) ?
				array_slice($toProcess, $groupSize * $i) :
				array_slice($toProcess, $groupSize * $i, $groupSize);
			file_put_contents("scan.tmp.$i", implode("\n", $group));
			$cmd=escapeshellarg($GLOBALS['argv'][0]);
			$cmdLine = "php -d memory_limit=1G $cmd -a -s ";
			if($config->getOutputFile()) {
				$outputFileName=$this->getMultipartFileName($config, $i);
				$cmdLine.=" -o ".escapeshellarg($outputFileName)." ";
			}
			if($config->getOutputLevel()==1) {
				$cmdLine.=" -v ";
			}
			if($config->getOutputLevel()==2) {
				$cmdLine.=" -v -v ";
			}
			$cmdLine.= escapeshellarg($config->getConfigFileName()) . " ".escapeshellarg("scan.tmp.$i");
			$output->outputExtraVerbose($cmdLine."\n");
			$file = popen($cmdLine, "r");
			$files[] = $file;
		}
		while (count($files) > 0) {
			$readFile = $files;
			$empty1 = $empty2 = null;
			$count = stream_select($readFile, $empty1, $empty2, 5);
			if ($count > 0) {
				foreach ($readFile as $index => $file) {
					echo fread($file, 1000);
					if (feof($file)) {
						unset($files[ array_search($file, $files) ]);
						if(!$error) {
							$error = pclose($file) == 0;
						}
						$output->outputExtraVerbose("Child process completed\n");
					}
				}
			} else {
				$output->output("T","Timed out waiting for next file to scan");
			}
		}
		for($i=0;$i<$config->getProcessCount();++$i) {
			unlink("scan.tmp.$i");
		}
		return $error ? 1 : 0;
	}

	function run(Config $config, OutputInterface $output) {
		$basePath=$config->getBasePath();
		$toProcess=[];
		$configArray = $config->getConfigArray();
		foreach($configArray['test'] as $directory) {
			$directory=$basePath."/".$directory;
			$output->outputVerbose("Directory: $directory\n");
			$it = new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS);
	 		$it2 = new \RecursiveIteratorIterator($it);
			$this->getPhase2Files($config, $output, $it2, $toProcess);
		}
		sort($toProcess);

		// First we split up the files by partition.
		// If we're running multiple child processes, then we'll split the list again.
		$groupSize = intval(count($toProcess) / $config->getPartitions());
		$toProcess = ($config->getPartitionNumber() == $config->getPartitions())
			? array_slice($toProcess, $groupSize * ($config->getPartitionNumber()-1))
			: array_slice($toProcess, $groupSize * ($config->getPartitionNumber()-1), $groupSize);


		$output->outputVerbose("Analyzing ".count($toProcess)." files\n");

		if($config->getProcessCount()>1) {
			return $this->runChildProcesses($config, $output, $toProcess);
		} else {
			return $this->phase2($config, $output, $toProcess);
		}
	}
}