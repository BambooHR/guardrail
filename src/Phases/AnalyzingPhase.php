<?php namespace BambooHR\Guardrail\Phases;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Checks\BaseCheck;
use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\DirectoryLister;
use BambooHR\Guardrail\Exceptions\UnknownTraitException;
use BambooHR\Guardrail\NodeVisitors\DocBlockNameResolver;
use BambooHR\Guardrail\NodeVisitors\PromotedPropertyVisitor;
use BambooHR\Guardrail\Output\SocketOutput;
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
use PhpParser\Node\Stmt\Enum_;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;
use BambooHR\Guardrail\Config;
use BambooHR\Guardrail\Exceptions\SocketException;
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
	/**
	 * @var StaticAnalyzer
	 */
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
		$traverser1->addVisitor($resolver = new NameResolver());
		$traverser1->addVisitor(new DocBlockNameResolver($resolver->getNameContext()));
		$traverser1->addVisitor(new PromotedPropertyVisitor());

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
		$processingCount = 0;

		$pm = new ProcessManager();

		$start = time();

		for ($fileNumber = 0; $fileNumber < $config->getProcessCount() && $fileNumber < count($toProcess); ++$fileNumber) {
			$socket = $pm->createChild(
				function ($socket) use ($config) {
					$this->runChildAnalyzer($socket, $config);
				});
			if (!$output->isTTY()) {
				$output->outputExtraVerbose(sprintf("%d - %s\n", $fileNumber, $toProcess[$fileNumber]));
			}
			$this->socket_write_all($socket, "ANALYZE " . $toProcess[$fileNumber] . "\n");
		}

		// Server process reports the errors and serves up new files to the list.
		$processDied = false;
		$bytes = 0;
		$pm->loopWhileConnections(
			function ($socket, $msg) use (&$processingCount, &$fileNumber, &$bytes, $output, $toProcess, $totalBytes, $start, $pm) {
				$processComplete = $this->processChildMessage($socket, $msg, $processingCount, $fileNumber, $bytes, $output, $toProcess, $totalBytes, $start, $pm);
				if ($processComplete) {
					return ProcessManager::CLOSE_CONNECTION;
				}
				return ProcessManager::READ_CONNECTION;
		});
		return ($processDied || $output->getErrorCount() > 0 ? 1 : 0);
	}

	protected function processChildMessage($socket, $msg, &$processingCount, &$fileNumber, &$bytes, OutputInterface $output, $toProcess, $totalBytes, $start, ProcessManager $pm) {
		$childPid = $pm->getPidForSocket($socket);
		//$output->outputExtraVerbose("parent received from $childPid: $msg\n");

		if ($msg === false) {
			echo "Error: Unexpected error reading from socket\n";
			return true;
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
				list($size,$analyzedFileName) = explode(' ', $details, 2);
				if ($fileNumber < count($toProcess)) {
					$bytes += intval($size);
					$this->socket_write_all($socket, "ANALYZE " . $toProcess[$fileNumber] . "\n");
					$fileNumber++;
				} else {
					$this->socket_write_all($socket, "TIMINGS\n");
				}

				$kbs=intdiv( intdiv($bytes, 1024), (time()-$start) ?: 1);
				["total"=>$errors, "displayed"=>$displayCount] =  $output->getErrorCounts();
				if ($output->isTTY()) {
					$white=$output->ttyContent("\33[97m");
					$red=$output->ttyContent("\33[31m");
					$reset=$output->ttyContent("\33[0m");
					printf("$white%d$reset/$white%d$reset, $white%d$reset/$white%d$reset MB ($white%d$reset%%), $white%d$reset KB/s $red%d$reset errors   \r",
						   $fileNumber, count($toProcess),
						   intdiv($bytes, 1024 * 1024), intdiv($totalBytes, 1024 * 1024),
						   intval(round(100 * $bytes / $totalBytes)),
						   $kbs,
						   $displayCount
					);
				} else {
					$output->output(".", sprintf("%d - %s", $fileNumber-1, $analyzedFileName));
				}
				break;
			case 'TIMINGS':
				$this->timingResults[] = json_decode(base64_decode($details), true);
				return true;
			default:
				$output->outputVerbose("Internal protocol Error.  Unknown message($message)\n");
				return true;
		}
		return false;
	}

	/**
	 * runChildAnalyzer
	 *
	 * @param  resource $socket
	 * @param  Config $config
	 * @return void
	 */
	protected function runChildAnalyzer($socket, Config $config) {
		$table = $config->getSymbolTable();
		if ($table instanceof PersistantSymbolTable) {
			$table->connect(0);
		}
		$this->initChildThread($socket, $config);
		$buffer = new SocketBuffer();
		$pid = getmypid();
		while (1) {
			$buffer->read($socket);
			foreach ($buffer->getMessages() as $receive) {
				$receive = trim($receive);
				if ($receive == "TIMINGS") {
					$this->socket_write_all($socket, "TIMINGS " . base64_encode(json_encode($this->analyzer->getTimingsAndCounts()) ). "\n");
					return;
				} else {
					list(, $file) = explode(' ', $receive, 2);
					$size = $this->analyzeFile($file, $config);
					$this->socket_write_all($socket, "ANALYZED $size $file\n");
				}
			}
		}
	}

	/**
	 * @param $callable
	 * @param $retries
	 *
	 * @return false|mixed
	 * @guardrail-ignore Standard.VariableFunctionCall:Variable
	 */
	protected function retryOnFalse($callable, $retries) {
		$succeeded = false;
		$tries = 0;
		while ($succeeded === false && $tries < $retries) {
			$succeeded = $callable();
			$tries++;
		}
		return $succeeded;
	}

	/* This function adapted from the PHP documentation on php.net */
	protected function socket_write_all($fp, $string) {
		$length = strlen($string);
		$fwrite=0;
		for ($written = 0; $written < $length; $written += $fwrite) {
			$fwrite = $this->retryOnFalse(function () use ($fp, $string, $written) {
				return @socket_write($fp, substr($string, $written));
			}, 3);
			if ($fwrite === false) {
				throw new SocketException(socket_strerror(socket_last_error($fp)));
			}
		}
		return $written;
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

		$output->outputVerbose("Partition sizes: " . $white . implode("$reset,$white ", $sizes)."$reset\n");

		$output->outputVerbose("Partition " . $white.($partitionNumber + 1).$reset . " analyzing " . $white.number_format(count($partialList) ). $reset." files (" . $white.number_format($sizes[$partitionNumber] ).$reset. " bytes)\n");
		return $this->phase2($config, $output, $partialList, $sizes[$partitionNumber]);
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
}