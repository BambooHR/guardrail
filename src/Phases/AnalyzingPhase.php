<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\Phases;

use BambooHR\Guardrail\Abstractions\Class_;
use BambooHR\Guardrail\Checks\BaseCheck;
use BambooHR\Guardrail\NodeVisitors\DocBlockNameResolver;
use BambooHR\Guardrail\NodeVisitors\DoWhileVisitor;
use BambooHR\Guardrail\Output\XUnitOutput;
use PhpParser\Comment;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\ParserFactory;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeTraverser;
use BambooHR\Guardrail\Config;
use BambooHR\Guardrail\NodeVisitors\TraitImportingVisitor;
use BambooHR\Guardrail\Util;
use BambooHR\Guardrail\NodeVisitors\StaticAnalyzer;
use BambooHR\Guardrail\Output\OutputInterface;


class AnalyzingPhase {
	function getPhase2Files(Config $config, OutputInterface $output, \RecursiveIteratorIterator $it2, &$toProcess) {
		$configArr = $config->getConfigArray();
		foreach ($it2 as $file) {
			if ($file->getExtension() == "php" && $file->isFile()) {
				if (isset($configArr['test-ignore']) && is_array($configArr['test-ignore']) && Util::matchesGlobs($config->getBasePath(), $file->getPathname(), $configArr['test-ignore'])) {
					continue;
				}
				$toProcess[] = $file->getPathname();
			}
		}
	}

	static function checkForSafeAutoloadNode($file, Node $node, OutputInterface $output) {
		if ($node instanceof Node\Stmt\Namespace_) {
			foreach ($node->stmts as $child) {
				if (!self::checkForSafeAutoloadNode($file, $child, $output)) {
					return false;
				}
			}
			return true;
		} else if (
			$node instanceof Node\Stmt\Nop ||
			$node instanceof Node\Expr\Include_ ||
			$node instanceof Node\Stmt\Class_ ||
			$node instanceof Node\Stmt\Interface_ ||
			$node instanceof  Node\Stmt\Trait_ ||
			$node instanceof Node\Stmt\Use_ ||
			$node instanceof Comment
		) {
			return true;
		} else {
			$output->emitError(__CLASS__, $file, $node->getLine(), BaseCheck::TYPE_AUTOLOAD_ERROR, "File is not safe to autoload.  It contains code other than a class:" . $node->getType());
			return false;
		}
	}

	function phase2(Config $config, OutputInterface $output, $toProcess) {

		$traverser1 = new NodeTraverser;
		$traverser1->addVisitor(new DocBlockNameResolver());
		$traverser1->addVisitor(new DoWhileVisitor());
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
					// We could do this with a node visitor, but it would be more complex and add unnecessary cycles when
					// it is so easy to inspect at the top level of the file.
					foreach ($stmts as $stmt) {
						if ( !self::checkForSafeAutoloadNode($file, $stmt, $output)) {
							break;
						}
					}

					$start = microtime(true);
					$analyzer->setFile($name);
					$stmts = $traverser1->traverse($stmts);
					$stmts = $traverser2->traverse($stmts);
					$traverser3->traverse($stmts);
					$end = microtime(true);
				}
			} catch (Error $e) {
				$output->emitError( __CLASS__, $file, 0, "Parse error", $e->getMessage() );
			} catch (\BambooHR\Guardrail\Exceptions\UnknownTraitException $e) {
				$output->emitError( __CLASS__, $file, 0, "Unknown trait error", $e->getMessage() );
			}

		}
		if ($output instanceof XUnitOutput) {
		//	print_r($output->getCounts());
		}
		return ($output->getErrorCount() > 0 ? 1 : 0);
	}

	function getMultipartFileName(Config $config, $part) {
		$outputFileName = $config->getOutputFile();
		$lastPart = strrpos($outputFileName, ".");
		if ($lastPart > 0) {
			$outputFileName = substr($outputFileName, 0, $lastPart + 1) . $part . ".xml";
		} else {
			$outputFileName = $outputFileName . $part;
		}
		return $outputFileName;
	}

	function runChildProcesses(Config $config, OutputInterface $output, array $toProcess) {
		$error = false;
		$files = [];
		$groupSize = intval(count($toProcess) / $config->getProcessCount());
		for ($i = 0; $i < $config->getProcessCount(); ++$i) {
			$group = ($i == $config->getProcessCount() - 1) ? array_slice($toProcess, $groupSize * $i) : array_slice($toProcess, $groupSize * $i, $groupSize);
			file_put_contents("scan.tmp.$i", implode("\n", $group));
			$cmd = escapeshellarg($GLOBALS['argv'][0]);
			$cmdLine = "php -d memory_limit=1G $cmd -a -s ";
			if ($config->getOutputFile()) {
				$outputFileName = $this->getMultipartFileName($config, $i);
				$cmdLine .= " -o " . escapeshellarg($outputFileName) . " ";
			}
			if ($config->getOutputLevel() == 1) {
				$cmdLine .= " -v ";
			}
			if ($config->getOutputLevel() == 2) {
				$cmdLine .= " -v -v ";
			}
			$cmdLine .= escapeshellarg($config->getConfigFileName()) . " " . escapeshellarg("scan.tmp.$i");
			$output->outputExtraVerbose($cmdLine . "\n");
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
						unset($files[array_search($file, $files)]);
						if (!$error) {
							$error = pclose($file) == 0;
						}
						$output->outputExtraVerbose("Child process completed\n");
					}
				}
			} else {
				$output->output("T", "Timed out waiting for next file to scan");
			}
		}
		for ($i = 0; $i < $config->getProcessCount(); ++$i) {
			unlink("scan.tmp.$i");
		}
		return $error ? 1 : 0;
	}

	/**
	 * run
	 *
	 * @param Config          $config Instance of Config
	 * @param OutputInterface $output Instance of OutputInterface
	 *
	 * @return int
	 */
	public function run(Config $config, OutputInterface $output) {
		$configArray = $config->getConfigArray();
		$baseDirectory = $config->getBasePath();
		$indexPaths = $configArray['test'];
		if (! Util::configDirectoriesAreValid($baseDirectory, $indexPaths)) {
			$output->output("Invalid or missing paths in your test config section.\n", "Invalid or missing paths in your test config section.\n");
			exit;
		}
		$output->outputVerbose("\nTest directories are valid: Starting Analysis");
		$toProcess = [];
		foreach ($indexPaths as $path) {
			$directory = Util::fullDirectoryPath($baseDirectory, $path);
			$output->outputVerbose("\n\nDirectory: $path\n");
			$it = new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS);
			 $it2 = new \RecursiveIteratorIterator($it);
			$this->getPhase2Files($config, $output, $it2, $toProcess);
		}
		sort($toProcess);

		// First we split up the files by partition.
		// If we're running multiple child processes, then we'll split the list again.
		$groupSize = intval(count($toProcess) / $config->getPartitions());
		$toProcess = ($config->getPartitionNumber() == $config->getPartitions()) ? array_slice($toProcess, $groupSize * ($config->getPartitionNumber() - 1)) : array_slice($toProcess, $groupSize * ($config->getPartitionNumber() - 1), $groupSize);

		$output->outputVerbose("\n\nAnalyzing " . count($toProcess) . " files\n");

		if ($config->getProcessCount() > 1) {
			return $this->runChildProcesses($config, $output, $toProcess);
		} else {
			return $this->phase2($config, $output, $toProcess);
		}
	}
}