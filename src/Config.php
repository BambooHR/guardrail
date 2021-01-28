<?php namespace BambooHR\Guardrail;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Checks\BaseCheck;
use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Exceptions\InvalidConfigException;
use BambooHR\Guardrail\Filters\FilterInterface;
use BambooHR\Guardrail\Filters\UnifiedDiffFilter;
use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\SymbolTable\SymbolTable;

/**
 * Class Config
 *
 * @package BambooHR\Guardrail
 */
class Config {
	const MEMORY_SYMBOL_TABLE = 1;
	const SQLITE_SYMBOL_TABLE = 2;
	const JSON_SYMBOL_TABLE = 3;

	/** @var int Number of analyzer processes to run.  If 1 then we don't run a child process. */
	private $processes = 1;

	/** @var string Directory containing the config file.  All files are relative to this directory */
	private $basePath = "";

	/**
	 *
	 * @var bool
	 */
	private $reindex = false;

	/** @var array nested array with the settings for what files to import */
	private $config = [];

	/** @var string */
	private $symbolTableFile = "symbol_table";

	/** @var int The number of partitions */
	private $partitions = 1;

	/** @var int Which partition this server is running */
	private $partitionNumber = 1;

	private $format = "xunit";

	/** @var string */
	private $outputFile = "";

	/** @var int MEMORY_SYMBOL_TABLE | SQLITE_SYMBOL_TABLE */
	private $preferredTable = self::MEMORY_SYMBOL_TABLE;

	/** @var \BambooHR\Guardrail\SymbolTable\SymbolTable */
	private $symbolTable = null;

	private $timings = false;

	/** @var string[]|false The list of files to process */
	private $fileList = false;


	/** @var bool */
	private $forceIndex = false;

	/** @var bool */
	private $forceAnalysis = false;

	/** @var string */
	private $configFileName = "";

	/** @var string[] */
	private $emitList = [];

	/** @var int */
	private $outputLevel = 0;

	/** @var FilterInterface */
	private $filter = null;

	/** @var string */
	private $filterFileName = "";

	/** @var bool */
	static private $useDocBlockForProperties = false;

	/** @var bool */
	static private $useDocBlockForReturnValue = false;

	/** @var bool */
	static private $useDocBlockForParameters = false;

	/** @var bool */
	static private $useDocBlockForInlineVars = false;

	/**
	 * @return void
	 */
	private function loadConfigVars() {
		if (isset($this->config) && array_key_exists('options', $this->config) && is_array($this->config['options'])) {
			foreach ($this->config['options'] as $key => $value) {
				if ($value === true) {
					switch ($key) {
						case "DocBlockReturns":
							self::$useDocBlockForReturnValue = true;
							break;
						case "DocBlockParams" :
							self::$useDocBlockForParameters = true;
							break;
						case "DocBlockProperties":
							self::$useDocBlockForProperties = true;
							break;
						case "DocBlockInlineVars":
							self::$useDocBlockForInlineVars = true;
					}
				}
			}
		}
	}

	/**
	 * Config constructor.
	 *
	 * @param array $argv The list of arguments
	 *
	 * @throws InvalidConfigException
	 */
	public function __construct($argv) {
		$this->parseArgv($argv);

		if (!$this->configFileName) {
			throw new InvalidConfigException;
		}

		$this->basePath = dirname(realpath($this->configFileName)) . "/";

		$fullPath = Util::fullDirectoryPath($this->getBasePath(), $this->configFileName);
		$jsonConfigValid = Util::jsonFileContentIsValid($fullPath);
		if (true !== $jsonConfigValid['success']) {
			echo $jsonConfigValid['message'] . "\n";
			throw new InvalidConfigException;
		}

		$this->config = json_decode(file_get_contents($this->configFileName), true);
		$this->loadConfigVars();

		if (isset($this->config['emit']) && is_array($this->config['emit'])) {
			$this->emitList = $this->config['emit'];
		}

		if (isset($this->config['emitMetrics']) && is_array($this->config['emitMetrics'])) {
			$this->emitList = $this->config['emitMetrics'];
		}

		if ($this->processes > 1 && $this->preferredTable == self::MEMORY_SYMBOL_TABLE) {
			$this->preferredTable = self::SQLITE_SYMBOL_TABLE;
		}

		if ($this->preferredTable == self::SQLITE_SYMBOL_TABLE || $this->preferredTable == self::JSON_SYMBOL_TABLE) {
			if (!file_exists($this->getSymbolTableFile())) {
				$this->forceIndex = true;
			}
			if ($this->forceIndex && file_exists($this->getSymbolTableFile())) {
				unlink($this->getSymbolTableFile());
			}

			if ($this->preferredTable == self::JSON_SYMBOL_TABLE) {
				$this->symbolTable = new \BambooHR\Guardrail\SymbolTable\JsonSymbolTable($this->getSymbolTableFile(), $this->getBasePath());
			} else {
				$this->symbolTable = new \BambooHR\Guardrail\SymbolTable\SqliteSymbolTable($this->getSymbolTableFile(), $this->getBasePath());
			}
		} else {
			$this->forceIndex = true;
			$this->symbolTable = new \BambooHR\Guardrail\SymbolTable\InMemorySymbolTable($this->getBasePath());
		}

	}

	/**
	 * @return bool
	 */
	static function shouldUseDocBlockForProperties() {
		return self::$useDocBlockForProperties;
	}

	/**
	 * @return bool
	 */
	static function shouldUseDocBlockForParameters() {
		return self::$useDocBlockForParameters;
	}

	/**
	 * @return bool
	 */
	static function shouldUseDocBlockForReturnValues() {
		return self::$useDocBlockForReturnValue;
	}

	/**
	 * @return bool
	 */
	static function shouldUseDocBlockForInlineVars() {
		return self::$useDocBlockForInlineVars;
	}

	/**
	 * @return bool
	 */
	public function shouldOutputTimings() {
		return $this->timings;
	}

	/**
	 * getPlugins
	 *
	 * @param SymbolTable     $index  Instance of SymbolTable
	 * @param OutputInterface $output Instance of OutputInterface
	 *
	 * @return BaseCheck[]
	 */
	public function getPlugins(SymbolTable $index, OutputInterface $output) {
		$plugins = [];
		if (isset($this->config['plugins']) && is_array($this->config['plugins'])) {
			foreach ($this->config['plugins'] as $fileName) {
				$fullPath = Util::fullDirectoryPath($this->basePath, $fileName);
				$function = require $fullPath;
				$plugins[] = call_user_func($function, $index, $output);
			}
		}
		return $plugins;
	}

	/**
	 * getOutputLevel
	 *
	 * @return int
	 */
	public function getOutputLevel() {
		return $this->outputLevel;
	}

	/**
	 * @return FilterInterface
	 */
	public function getFilter() {
		return $this->filter;
	}

	/**
	 * @return string
	 */
	public function getFilterFileName() {
		return $this->filterFileName;
	}

	/**
	 * showStandardTests
	 *
	 * @return void
	 */
	public function showStandardTests() {
		echo "The following constants are supported:\n    " . implode("\n    ", ErrorConstants::getConstants()) . "\n";
	}

	/**
	 * parseArgv
	 *
	 * @param array $argv List of arguments
	 *
	 * @return void
	 * @throws InvalidConfigException
	 */
	private function parseArgv(array $argv) {
		$nextArg = 0;
		for ($argCount = 1; $argCount < count($argv); ++$argCount) {
			switch ($argv[$argCount]) {
				case '-a':
					$this->forceAnalysis = true;
					break;

				case '--format':
					if (++$argCount >= count($argv) || !in_array($argv[$argCount], ["xunit", "text", "counts"])) {
						throw new InvalidConfigException;
					}
					$this->format = $argv[$argCount];
					break;

				case '--timings':
					$this->timings = true;
					break;

				case '-l':
				case '--list':
					$this->showStandardTests();
					exit();
					break;
				case '-i':
					$this->forceIndex = true;
					break;
				case '-s':
					$this->preferredTable = self::SQLITE_SYMBOL_TABLE;
					break;
				case '-m':
					$this->preferredTable = self::MEMORY_SYMBOL_TABLE;
					break;
				case '-j':
					$this->preferredTable = self::JSON_SYMBOL_TABLE;
					break;
				case '-p':
					$params = [];
					if ($argCount + 1 >= count($argv) || !preg_match('/^([0-9]+)\\/([0-9]+)$/', $argv[$argCount + 1], $params)) {
						throw new InvalidConfigException;
					}
					++$argCount;
					list($wholeMatch, $this->partitionNumber, $this->partitions) = $params;
					if ($this->partitionNumber < 1 || $this->partitionNumber > $this->partitions) {
						throw new InvalidConfigException;
					}
					break;
				case '-v':
					$this->outputLevel++;
					break;
				case '-n':
					if ($argCount + 1 >= count($argv)) {
						throw new InvalidConfigException;
					}
					$this->processes = intval($argv[++$argCount]);
					break;
				case '-f':
					if ($argCount + 1 >= count($argv)) {
						throw new InvalidConfigException;
					}
					$this->preferredTable = self::SQLITE_SYMBOL_TABLE;
					$this->fileList = [$argv[++$argCount]];
					$this->reindex = true;
					break;
				case '-o':
					if ($argCount + 1 >= count($argv)) {
						throw new InvalidConfigException;
					}
					$this->outputFile = $argv[++$argCount];
					break;
				case '--diff':
					if ($argCount + 1 >= count($argv)) {
						throw new InvalidConfigException();
					}
					$this->filterFileName = $argv[++$argCount];
					$filter = UnifiedDiffFilter::importFile(
						realpath($this->filterFileName)
					);
					$filter->display();
					$this->filter = $filter;
					break;
				case '-h':
				case '--help':
					throw new InvalidConfigException;
					break;
				default:
					switch ($nextArg) {
						case 0:
							$this->configFileName = $argv[$argCount];
							break;
						case 1:
							$this->fileList = explode("\n", file_get_contents($argv[$argCount]));
							break;
						default:
							throw new InvalidConfigException;
					}
					$nextArg++;
			}
		}
		if ($this->preferredTable == self::MEMORY_SYMBOL_TABLE) {
			$this->forceIndex = true;
		}
		if (count($argv) < 2) {
			throw new InvalidConfigException;
		}
	}

	/**
	 * getProcessCount
	 *
	 * @return int
	 */
	public function getProcessCount() {
		return $this->processes;
	}

	/**
	 * getConfigArray
	 *
	 * @return array|mixed
	 */
	public function getConfigArray() {
		return $this->config;
	}

	/**
	 * hasFileList
	 *
	 * @return bool
	 */
	public function hasFileList() {
		return $this->fileList !== false;
	}

	/**
	 * getFileList
	 *
	 * @return false|\string[]
	 */
	public function getFileList() {
		return $this->fileList;
	}

	/**
	 * getConfigFileName
	 *
	 * @return string
	 */
	public function getConfigFileName() {
		return $this->configFileName;
	}

	/**
	 * getPartitions
	 *
	 * @return int
	 */
	public function getPartitions() {
		return $this->partitions;
	}

	/**
	 * getPartitionNumber
	 *
	 * @return int
	 */
	public function getPartitionNumber() {
		return $this->partitionNumber;
	}

	/**
	 * getBasePath
	 *
	 * @return string
	 */
	public function getBasePath() {
		return $this->basePath;
	}

	/**
	 * getSymbolTable
	 *
	 * @return SymbolTable
	 */
	public function getSymbolTable() {
		return $this->symbolTable;
	}

	/**
	 * shouldIndex
	 *
	 * @return bool
	 */
	public function shouldIndex() {
		return $this->forceIndex;
	}

	/**
	 * shouldAnalyze
	 *
	 * @return bool
	 */
	public function shouldAnalyze() {
		return $this->forceAnalysis;
	}

	/**
	 * @return string
	 */
	public function getOutputFormat() {
		return $this->format;
	}

	/**
	 * getSymbolTableFile
	 *
	 * @return string
	 */
	private function getSymbolTableFile() {
		return $this->basePath . "/" . $this->symbolTableFile .
			($this->preferredTable == self::SQLITE_SYMBOL_TABLE ? ".sqlite3" : ".json");
	}

	/**
	 * shouldReindex
	 *
	 * @return bool
	 */
	public function shouldReindex() {
		return $this->reindex;
	}

	/**
	 * getEmitList
	 *
	 * @return mixed|\string[]
	 */
	public function getEmitList() {
		return $this->emitList;
	}

	public function getMetricEmitList() {
		return [
			[
				'emit' => 'Standard.Method.Call',
				'threshold' => [
					'data.sharedNamespaceParts' => 3,
					'operator' => '<'
				]
			],
			'Standard.*'
		];
	}

	/**
	 * processCount
	 *
	 * @return int
	 */
	public function processCount() {
		return $this->processes;
	}

	/**
	 * getOutputFile
	 *
	 * @return string
	 */
	public function getOutputFile() {
		if ($this->partitions > 1) {
			$lastPart = strrpos($this->outputFile, ".");
			if ($lastPart > 0) {
				return substr($this->outputFile, 0, $lastPart + 1) . $this->partitionNumber . ".xml";
			} else {
				return $this->outputFile . $this->partitionNumber;
			}
		} else {
			return $this->outputFile;
		}
	}

	public function getMetricOutputFile() {
		return "metrics.json";
	}
}