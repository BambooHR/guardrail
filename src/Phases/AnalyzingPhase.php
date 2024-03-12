<?php namespace BambooHR\Guardrail\Phases;

/**
 * Guardrail.  Copyright (c) 2016-2024, BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Checks\BaseCheck;
use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Config;
use BambooHR\Guardrail\DirectoryLister;
use BambooHR\Guardrail\Exceptions\UnknownTraitException;
use BambooHR\Guardrail\NodeVisitors\DocBlockNameResolver;
use BambooHR\Guardrail\NodeVisitors\PromotedPropertyVisitor;
use BambooHR\Guardrail\NodeVisitors\StaticAnalyzer;
use BambooHR\Guardrail\NodeVisitors\TraitImportingVisitor;
use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\Output\SocketOutput;
use BambooHR\Guardrail\Phases\Processes\Child\AnalyzingChildProcess;
use BambooHR\Guardrail\Phases\Processes\Parent\AnalyzingParentProcess;
use BambooHR\Guardrail\Socket;
use BambooHR\Guardrail\Util;
use PhpParser\Comment;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Expr\Include_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

/**
 * Class AnalyzingPhase
 *
 * @package BambooHR\Guardrail\Phases
 */
class AnalyzingPhase {
	private $traversers = [];
	private $parser = null;

	private StaticAnalyzer $analyzer;

	private OutputInterface $output;

	private array $timingResults = [[],[]];

	/**
	 * AnalyzingPhase constructor.
	 */
	function __construct() { }


	function getTimingResults():array {
		return $this->timingResults;
	}


	function initParser(Config $config, OutputInterface $output) {
		$traverser1 = new NodeTraverser;
		$traverser1->addVisitor($resolver = new NameResolver());
		$traverser1->addVisitor(new DocBlockNameResolver($resolver->getNameContext()));
		$traverser1->addVisitor(new PromotedPropertyVisitor());

		$traverser2 = new NodeTraverser();
		$traverser2->addVisitor(new TraitImportingVisitor($config->getSymbolTable()));

		$traverser3 = new NodeTraverser;
		$traverser3->addVisitor($this->analyzer = new StaticAnalyzer($config->getSymbolTable(), $output, $config ));

		$this->output = $output;

		$this->traversers = [$traverser1, $traverser2, $traverser3];
		$this->parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
	}

	function getAnalyzer() {
		return $this->analyzer;
	}

	/**
	 * @return array
	 */
	function setTimingResults($timingResults) {
		$ret = [];

		list($timings, $counts) = $timingResults;
		foreach ($timings as $class => $time) {
			$ret[$class]['time'] = ($ret[$class]['time'] ?? 0) + $time;
			$ret[$class]['count'] = ($ret[$class]['count'] ?? 0) + $counts[$class];
		}

		uasort( $ret, fn($first, $second) => $second['time'] <=> $first['time'] );
		$this->timingResults  = $ret;
	}

	/**
	 * getPhase2Files
	 *
	 * @param Config    $config    Instance of Config
	 * @param \Iterator $it2       Instance of RecursiveIteratorIterator
	 * @param array     $toProcess The content to process
	 *
	 * @return void
	 */
	public function getPhase2Files(Config $config, \Iterator $it2, &$toProcess) {
		$configArr = $config->getConfigArray();
		foreach ($it2 as $file) {
			if (preg_match('/\\.php$/', $file) && is_file($file)) {
				if (isset($configArr['test-ignore']) && is_array($configArr['test-ignore']) && Util::matchesGlobs($config->getBasePath(), $file, $configArr['test-ignore'])) {
					continue;
				}
				$toProcess[] = [$file, filesize($file)];
			}
		}
	}

	/**
	 * checkForSafeAutoloadNode
	 *
	 * @param string          $file   The file
	 * @param Node            $node   Instance of Node
	 * @param OutputInterface $output Instance of OutputInterface
	 *
	 * @return bool
	 */
	static public function checkForSafeAutoloadNode($file, Node $node, OutputInterface $output) {
		if ($node instanceof Node\Stmt\Declare_ && $node->stmts!==null) {
			foreach($node->stmts as $child) {
				if (!self::checkForSafeautoloadNode($file, $child, $output)) {
					return false;
				}
			}
			return true;
		} else if ($node instanceof Namespace_) {
			foreach ($node->stmts as $child) {
				if (!self::checkForSafeAutoloadNode($file, $child, $output)) {
					return false;
				}
			}
			return true;
		} else if (
			$node instanceof Nop ||
			$node instanceof Class_ ||
			$node instanceof Interface_ ||
			$node instanceof Trait_ ||
			$node instanceof Use_ ||
			$node instanceof Node\Stmt\GroupUse ||
			$node instanceof Comment ||
			$node instanceof Enum_ ||
			$node instanceof Node\Stmt\Declare_
		) {
			return true;
		} elseif ($node instanceof Node\Stmt\Expression && $node->expr instanceof Include_) {
			return true;
		} else {
			$output->emitError(__CLASS__, $file, $node->getLine(), BaseCheck::TYPE_AUTOLOAD_ERROR, "File is not safe to autoload.  It contains code other than a class:" . $node->getType());
			return false;
		}
	}

	/**
	 * @param string $file   The file to scan
	 * @param Config $config The application config
	 * @return int
	 */
	function analyzeFile($file, Config $config) {
		$name = Util::removeInitialPath($config->getBasePath(), $file);
		$fileData = file_get_contents($file);
		return $this->analyzeString($name,$fileData);
	}

	/**
	 * phase2
	 *
	 * @param Config          $config    Instance of Config
	 * @param OutputInterface $output    Instance of OutputInterface
	 * @param array           $toProcess The content to process
	 *
	 * @return int
	 */
	public function phase2(Config $config, OutputInterface $output, $toProcess, $totalBytes) {

		$config->getSymbolTable()->connect(0);

		$pm = new AnalyzingParentProcess($toProcess, $totalBytes, $output);
		$pm->run($this, $config);
		$this->setTimingResults($pm->getTimings());
		return ($output->getErrorCount() > 0 ? 1 : 0);
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

		$white=$output->ttyContent("\33[97m");
		$reset=$output->ttyContent("\33[0m");
		$output->outputVerbose("Test directories are valid: Starting Analysis\n");
		$toProcess = [];
		if ($config->hasFileList()) {
			foreach ($config->getFileList() as $fileName) {
				$toProcess[] = [$fileName, filesize($baseDirectory . "/" . $fileName)];
			}
		} else {
			foreach ($indexPaths as $path) {
				$tmpDirectory = Util::fullDirectoryPath($baseDirectory, $path);
				$output->outputVerbose(
					"Directory: " .
					$white .
					$path .
					$reset .
					$output->ttyContent("\33[0m") .
					"\n"
				);
				$it2 = DirectoryLister::getGenerator($tmpDirectory);
				$this->getPhase2Files($config, $it2, $toProcess);
			}
		}

		$output->outputVerbose("Allotting work for " . $white . $config->getPartitions() . $reset . " partitions\n");

		// Sort all the files first by size and second by name.
		// Once we have a list that is roughly even, then we can split
		// it up more or less evenly rather than have a cluster of large files
		// all in one chunk.
		usort($toProcess, function ($fileA, $fileB) {
			if (intval($fileA[1]) == intval($fileB[1])) {
				return strcmp($fileA[0], $fileB[0]);
			} else {
				return intval($fileA[1]) > intval($fileB[1]) ? -1 : +1;
			}
		});

		list($partialList, $partitionNumber, $sizes) = $this->partition($config, $toProcess);
		//$partialList = $this->reshuffle($partialList);

		$output->outputVerbose("Partition sizes: " . $white . implode("$reset,$white ", $sizes)."$reset\n");

		$output->outputVerbose("Partition " . $white.($partitionNumber + 1).$reset . " analyzing " . $white.number_format(count($partialList) ). $reset." files (" . $white.number_format($sizes[$partitionNumber] ).$reset. " bytes)\n");
		return $this->phase2($config, $output, $partialList, $sizes[$partitionNumber]);
	}

	/**
	 *
	 * Swaps every other entry from the start of the list with every other entry from the end of the list.
	 * The intention is to even out I/O not doing all the big files at once.
	 */
	function reshuffle(array $partialList):array {
		$last=count($partialList)-1;
		for($i=0;$i < $last >> 1; $i+=2) {
			$tmp=$partialList[$i];
			$partialList[$i]=$partialList[$last-$i];
			$partialList[$last-$i] = $tmp;
		}
		return $partialList;
	}

	/**
	 * @param bool|string $fileData
	 * @param string $file
	 * @param bool|string $name
	 * @return void
	 */
	public function analyzeString(string $name, string $fileData): int
	{
		try {
			$stmts = $this->parser->parse($fileData);
			if ($stmts) {
				// We could do this with a node visitor, but it would be more complex and add unnecessary cycles when
				// it is so easy to inspect at the top level of the file.
				foreach ($stmts as $stmt) {
					if (!self::checkForSafeAutoloadNode($name, $stmt, $this->output)) {
						break;
					}
				}

				$this->analyzer->setFile($name);
				foreach ($this->traversers as $traverser) {
					$traverser->traverse($stmts);
				}
				return strlen($fileData);
			}
		} catch (Error $exception) {
			$msg = preg_replace("/on line [0-9]+$/", "", $exception->getMessage());
			$this->output->emitError(__CLASS__, $name, $exception->getStartLine(), ErrorConstants::TYPE_PARSE_ERROR, $msg);
		} catch (UnknownTraitException $exception) {
			$this->output->emitError(__CLASS__, $name, 0, ErrorConstants::TYPE_UNKNOWN_CLASS, $exception->getMessage());
		}
		return 0;
	}

	/**
	 * @param Config $config
	 * @param array $toProcess
	 * @return array
	 */
	public function partition(Config $config, array $toProcess): array {
		$partialList = [];

		// Attempt to evenly balance all partitions.
		// 1. Start with a sorted list, biggest file first
		// 2. For each file put it in the emptiest partition.
		// 3. If we are the emptiest partition, then make sure to add it to our file list, otherwise just increment a total.
		$partitionNumber = $config->getPartitionNumber() - 1 ;
		$sizes = array_fill(0, $config->getPartitions(), 0); // Initialize an array of 0 sizes.
		foreach ($toProcess as $file) {
			$minIndex = 0;
			$minSize = $sizes[0];
			foreach ($sizes as $index => $size) {
				if ($size < $minSize) {
					$minIndex = $index;
					$minSize = $size;
				}
			}
			$sizes[$minIndex] += $file[1];
			if ($minIndex == $partitionNumber) {
				$partialList[] = $file[0];
			}
		}
		return array($partialList, $partitionNumber, $sizes);
	}
}