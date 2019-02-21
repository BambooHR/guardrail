<?php namespace BambooHR\Guardrail\Phases;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Checks\BaseCheck;
use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Exceptions\UnknownTraitException;
use BambooHR\Guardrail\NodeVisitors\DocBlockNameResolver;
use BambooHR\Guardrail\NodeVisitors\DoWhileVisitor;
use BambooHR\Guardrail\Output\SocketOutput;
use BambooHR\Guardrail\Output\XUnitOutput;
use BambooHR\Guardrail\ProcessManager;
use BambooHR\Guardrail\SocketBuffer;
use BambooHR\Guardrail\SymbolTable\PersistantSymbolTable;
use FilesystemIterator;
use PhpParser\Comment;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\Node\Expr\Include_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;
use BambooHR\Guardrail\Config;
use BambooHR\Guardrail\NodeVisitors\TraitImportingVisitor;
use BambooHR\Guardrail\Util;
use BambooHR\Guardrail\NodeVisitors\StaticAnalyzer;
use BambooHR\Guardrail\Output\OutputInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Class AnalyzingPhase
 *
 * @package BambooHR\Guardrail\Phases
 */
class AnalyzingPhase {
	private $traversers = [];
	private $parser = null;
	private $analyzer;
	private $timingResults = [];

	/** @var OutputInterface Child processes will overwrite this in order to send data over the socket. */
	private $output = null;

	/**
	 * AnalyzingPhase constructor.
	 * @param OutputInterface $output Where to send output
	 */
	function __construct(OutputInterface $output) {
		$this->output = $output;
	}

	/**
	 * @param resource $socket The pipe to read/write from
	 * @param Config   $config The application config
	 * @return void
	 */
	function initChildThread($socket, Config $config) {
		$this->output = new SocketOutput($config, $socket);
		$this->initParser($config, $this->output);
	}

	/**
	 * @param Config          $config -
	 * @param OutputInterface $output -
	 * @return void
	 */
	function initParser(Config $config, OutputInterface $output) {
		$traverser1 = new NodeTraverser;
		$traverser1->addVisitor(new DocBlockNameResolver());
		$traverser1->addVisitor(new DoWhileVisitor());

		$traverser2 = new NodeTraverser();
		$traverser2->addVisitor(new TraitImportingVisitor($config->getSymbolTable()));

		$this->analyzer = new StaticAnalyzer($config->getBasePath(), $config->getSymbolTable(), $output, $config);
		$traverser3 = new NodeTraverser;
		$traverser3->addVisitor($this->analyzer);

		$this->traversers = [$traverser1, $traverser2, $traverser3];
		$this->parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
	}

	/**
	 * @return array
	 */
	function getTimingResults() {
		$ret = [];
		foreach ($this->timingResults as $timingArr) {
			list($timings, $counts) = $timingArr;
			foreach ($timings as $class => $time) {
				$ret[$class]['time'] = (isset($ret[$class]['time']) ? $ret[$class]['time'] : 0) + $time;
				$ret[$class]['count'] = (isset($ret[$class]['count']) ? $ret[$class]['count'] : 0) + $counts[$class];

			}
		}
		uasort( $ret, function($first, $second) {
			return ($first['time'] > $second['time'] ? -1 : ($first['time'] < $second['time'] ? 1 : 0));
		});

		return $ret;
	}

	/**
	 * getPhase2Files
	 *
	 * @param Config                    $config    Instance of Config
	 * @param RecursiveIteratorIterator $it2       Instance of RecursiveIteratorIterator
	 * @param array                     $toProcess The content to process
	 *
	 * @return void
	 */
	public function getPhase2Files(Config $config, RecursiveIteratorIterator $it2, &$toProcess) {
		$configArr = $config->getConfigArray();
		foreach ($it2 as $file) {
			if ($file->getExtension() == "php" && $file->isFile()) {
				if (isset($configArr['test-ignore']) && is_array($configArr['test-ignore']) && Util::matchesGlobs($config->getBasePath(), $file->getRealPath(), $configArr['test-ignore'])) {
					continue;
				}
				$toProcess[] = [$file->getPathname(), $file->getSize()];
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
		if ($node instanceof Namespace_) {
			foreach ($node->stmts as $child) {
				if (!self::checkForSafeAutoloadNode($file, $child, $output)) {
					return false;
				}
			}
			return true;
		} else if (
			$node instanceof Nop ||
			$node instanceof Include_ ||
			$node instanceof Class_ ||
			$node instanceof Interface_ ||
			$node instanceof  Trait_ ||
			$node instanceof Use_ ||
			$node instanceof Comment
		) {
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
		try {
			$name = Util::removeInitialPath($config->getBasePath(), $file);

			$fileData = file_get_contents($file);
			$stmts = $this->parser->parse($fileData);
			if ($stmts) {
				// We could do this with a node visitor, but it would be more complex and add unnecessary cycles when
				// it is so easy to inspect at the top level of the file.
				foreach ($stmts as $stmt) {
					if ( !self::checkForSafeAutoloadNode($file, $stmt, $this->output)) {
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
			$this->output->emitError( __CLASS__, $file, $exception->getStartLine(), ErrorConstants::TYPE_PARSE_ERROR, $msg );
		} catch (UnknownTraitException $exception) {
			$this->output->emitError( __CLASS__, $file, 0, ErrorConstants::TYPE_UNKNOWN_CLASS, $exception->getMessage() );
		}

		return 0;
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
	public function phase2(Config $config, OutputInterface $output, $toProcess) {
		$processingCount = 0;

		$pm = new ProcessManager();

		$start = microtime(true);

		for ($fileNumber = 0; $fileNumber < $config->getProcessCount() && $fileNumber < count($toProcess); ++$fileNumber) {
			$socket = $pm->createChild(
				function ($socket) use ($fileNumber, $config) {
					$table = $config->getSymbolTable();
					if ($table instanceof PersistantSymbolTable) {
						$table->connect(0);
					}
					$this->initChildThread($socket, $config);
					$buffer = new SocketBuffer();
					while (1) {
						$buffer->read($socket);
						foreach ($buffer->getMessages() as $receive) {
							$receive = trim($receive);
							if ($receive == "TIMINGS") {
								socket_write($socket, "TIMINGS " . json_encode($this->analyzer->getTimingsAndCounts()) . "\n");
								return 0;
							} else {
								list($command, $file) = explode(' ', $receive, 2);
								$size = $this->analyzeFile($file, $config);
								socket_write($socket, "ANALYZED $size $file\n");
							}
						}
					}

				});
			socket_write($socket, "ANALYZE " . $toProcess[$fileNumber] . "\n");
		}

		// Server process reports the errors and serves up new files to the list.
		$processDied = false;
		$bytes = 0;
		$pm->loopWhileConnections(
			function ($socket, $msg) use (&$processingCount, &$fileNumber, &$bytes, $output, $toProcess, $start, &$pm, &$processDied) {
				if ($msg === false) {
					$processDied = true;
					echo "Error: Unexpected error reading from socket\n";
					return ProcessManager::CLOSE_CONNECTION;
				}
				$msg = trim($msg);
				list($message, $details) = explode(' ', $msg, 2);
				switch ($message) {
					case 'VERBOSE':
						$this->output->outputVerbose(base64_decode($details));
						break;
					case 'EXTRAVERBOSE':
						$this->output->outputExtraVerbose(base64_decode($details));
						break;
					case 'OUTPUT':
						$vars = unserialize(base64_decode($details));
						$this->output->output($vars['v'], $vars['ev']);
						break;
					case 'ERROR' :
						$vars = unserialize(base64_decode($details));
						$this->output->emitError(
							$vars['className'],
							$vars['file'],
							$vars['line'],
							$vars['type'],
							$vars['message']
						);
						break;
					case 'ANALYZED':
						list($size, $name) = explode(' ', $details, 2);
						$output->output(".", sprintf("%d - %s", ++$processingCount, $name));
						if ($fileNumber < count($toProcess)) {
							$bytes += intval($size);
							socket_write($socket, "INDEX " . $toProcess[$fileNumber] . "\n");
							$fileNumber++;
						} else {
							socket_write($socket, "TIMINGS\n");
						}
						if ($fileNumber % 50 == 0) {
							$output->outputExtraVerbose(
								sprintf("Processing %.1f KB/second", $bytes / 1024 / (microtime(true) - $start))
							);
						}
						break;
					case 'TIMINGS':
						$this->timingResults[] = json_decode($details, true);
						return ProcessManager::CLOSE_CONNECTION;
				}
				return ProcessManager::READ_CONNECTION;
		});
		return ($processDied || $output->getErrorCount() > 0 ? 1 : 0);
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
		$output->outputVerbose("Test directories are valid: Starting Analysis\n");
		$toProcess = [];
		if ($config->hasFileList()) {
			foreach ($config->getFileList() as $fileName) {
				$toProcess[] = [$fileName, filesize($baseDirectory . "/" . $fileName)];
			}
		} else {
			foreach ($indexPaths as $path) {
				$tmpDirectory = Util::fullDirectoryPath($baseDirectory, $path);
				$output->outputVerbose("Directory: $path\n");
				$it = new RecursiveDirectoryIterator($tmpDirectory, FilesystemIterator::SKIP_DOTS);
				$it2 = new RecursiveIteratorIterator($it);
				$this->getPhase2Files($config, $it2, $toProcess);
			}
		}

		$output->outputVerbose("\nAllotting work for " . $config->getPartitions() . " partitions\n");

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

		$partialList = [];

		// Attempt to evenly balance all partitions.
		// 1. Start with a sorted list, biggest file first
		// 2. For each file put it in the emptiest partition.
		// 3. If we are the emptiest partition, then make sure to add it to our file list, otherwise just increment a total.
		$partitionNumber = $config->getPartitionNumber() - 1;
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

		$output->outputVerbose("Sizes: " . implode(", ", $sizes) . "\n");

		$output->outputVerbose("\nPartition " . ($partitionNumber + 1) . " analyzing " . count($partialList) . " files (" . $sizes[$partitionNumber] . " bytes)\n");
		return $this->phase2($config, $output, $partialList);
	}
}