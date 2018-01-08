<?php namespace BambooHR\Guardrail\Phases;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Checks\BaseCheck;
use BambooHR\Guardrail\Exceptions\UnknownTraitException;
use BambooHR\Guardrail\NodeVisitors\DocBlockNameResolver;
use BambooHR\Guardrail\NodeVisitors\DoWhileVisitor;
use BambooHR\Guardrail\Output\SocketOutput;
use BambooHR\Guardrail\Output\XUnitOutput;
use BambooHR\Guardrail\ProcessManager;
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
		$traverser1 = new NodeTraverser;
		$traverser1->addVisitor(new DocBlockNameResolver());
		$traverser1->addVisitor(new DoWhileVisitor());

		$traverser2 = new NodeTraverser();
		$traverser2->addVisitor(new TraitImportingVisitor($config->getSymbolTable()));

		$this->analyzer = new StaticAnalyzer($config->getBasePath(), $config->getSymbolTable(), $this->output, $config);
		$traverser3 = new NodeTraverser;
		$traverser3->addVisitor($this->analyzer);

		$this->traversers = [$traverser1, $traverser2, $traverser3];
		$this->parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
	}

	/**
	 * getPhase2Files
	 *
	 * @param Config                    $config    Instance of Config
	 * @param RecursiveIteratorIterator $it2       Instance of RecursiveIteratorIterator
	 * @param string                    $toProcess The content to process
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
				$toProcess[] = $file->getPathname();
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
	 * @param string $file            The file to scan
	 * @param int    $processingCount The number of files scanned
	 * @param Config $config          The application config
	 * @return int
	 */
	function analyzeFile($file, $processingCount, Config $config) {
		try {
			$name = Util::removeInitialPath($config->getBasePath(), $file);

			$processingCount++;
			//echo " - $processingCount:" . $file . "\n";
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
			$this->output->emitError( __CLASS__, $file, 0, "Parse error", $exception->getMessage() );
		} catch (UnknownTraitException $exception) {
			$this->output->emitError( __CLASS__, $file, 0, "Unknown trait error", $exception->getMessage() );
		}
	}

	/**
	 * phase2
	 *
	 * @param Config          $config    Instance of Config
	 * @param OutputInterface $output    Instance of OutputInterface
	 * @param string          $toProcess The content to process
	 *
	 * @return int
	 */
	public function phase2(Config $config, OutputInterface $output, $toProcess) {
		$processingCount = 0;

		$pm = new ProcessManager();

		$start = microtime(true);

		for ($fileNumber = 0; $fileNumber < $config->getProcessCount() && $fileNumber < count($toProcess); ++$fileNumber) {
			$socket = $pm->createChild(
				function($socket) use ($config, &$processingCount) {
					$config->getSymbolTable()->connect();
					$this->initChildThread($socket, $config);
					while (1) {
						$receive = trim(socket_read($socket, 200, PHP_NORMAL_READ));
						if ($receive == "DONE") {
							return 0;
						} else {
							list($command, $file) = explode(' ', $receive, 2 );
							$size = $this->analyzeFile($file, $processingCount, $config);
							socket_write($socket, "ANALYZED $size $file\n");
						}
					}

			});
			socket_write($socket, "ANALYZE " . $toProcess[$fileNumber] . "\n");
		}

		// Server process reports the errors and serves up new files to the list.
		$pm->loopWhileConnections(
			function ($socket, $msg) use (&$it, &$fileNumber, &$bytes, $output, $toProcess, $start) {
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
						if ($fileNumber < count($toProcess)) {
							list($size, $name) = explode(' ', $details, 2);
							$bytes += $size;
							$output->output(".", sprintf("%d - %s", $fileNumber + 1, $toProcess[$fileNumber]));
							socket_write($socket, "INDEX " . $toProcess[$fileNumber] . "\n");
							$fileNumber++;
						} else {
							socket_write($socket, "DONE\n");
							return ProcessManager::CLOSE_CONNECTION;
						}
						if ($fileNumber % 50 == 0) {
							$output->outputExtraVerbose(
								sprintf("Processing %.1f KB/second", $bytes / 1024 / (microtime(true) - $start))
							);
						}
						break;
				}
				return ProcessManager::READ_CONNECTION;
		});
		return ($output->getErrorCount() > 0 ? 1 : 0);
	}

	/**
	 * getMultipartFileName
	 *
	 * @param Config $config Instance of Config
	 * @param string $part   The part to process
	 *
	 * @return string
	 */
	public function getMultipartFileName(Config $config, $part) {
		$outputFileName = $config->getOutputFile();
		$lastPart = strrpos($outputFileName, ".");
		if ($lastPart > 0) {
			$outputFileName = substr($outputFileName, 0, $lastPart + 1) . $part . ".xml";
		} else {
			$outputFileName = $outputFileName . $part;
		}
		return $outputFileName;
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
			$tmpDirectory = Util::fullDirectoryPath($baseDirectory, $path);
			$output->outputVerbose("\n\nDirectory: $path\n");
			$it = new RecursiveDirectoryIterator($tmpDirectory, FilesystemIterator::SKIP_DOTS);
			$it2 = new RecursiveIteratorIterator($it);
			$this->getPhase2Files($config, $it2, $toProcess);
		}

		// First we split up the files by partition.
		// If we're running multiple child processes, then we'll split the list again.
		$groupSize = intval(count($toProcess) / $config->getPartitions());
		$toProcess = ($config->getPartitionNumber() == $config->getPartitions()) ? array_slice($toProcess, $groupSize * ($config->getPartitionNumber() - 1)) : array_slice($toProcess, $groupSize * ($config->getPartitionNumber() - 1), $groupSize);

		$output->outputVerbose("\n\nAnalyzing " . count($toProcess) . " files\n");

		return $this->phase2($config, $output, $toProcess);
	}
}