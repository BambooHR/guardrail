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

	/** @var int Number of analyzer processes to run.  If 1 then we don't run a child process.  */
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

	/** @var string  */
	private $symbolTableFile = "symbol_table.sqlite3";

	/** @var int The number of partitions */
	private $partitions = 1;

	/** @var int Which partition this server is running  */
	private $partitionNumber = 1;

	/** @var string  */
	private $outputFile = "";

	/** @var int MEMORY_SYMBOL_TABLE | SQLITE_SYMBOL_TABLE */
	private $preferredTable = self::MEMORY_SYMBOL_TABLE;

	/** @var \BambooHR\Guardrail\SymbolTable\SymbolTable */
	private $symbolTable = null;

	/** @var string[]|false The list of files to process */
	private $fileList = false;


	/** @var bool  */
	private $forceIndex = false;

	/** @var bool  */
	private $forceAnalysis = false;

	/** @var string */
	private $configFileName = "";

	/** @var string[] */
	private $emitList = [];

	/** @var int  */
	private $outputLevel = 0;

	/** @var FilterInterface */
	private $filter = null;

	/** @var string */
	private $filterFileName = "";

	/**
	 * Config constructor.
	 *
	 * @param string $argv The list of arguments
	 *
	 * @throws InvalidConfigException
	 */
	public function __construct($argv) {
		$this->parseArgv($argv);

		if (!$this->configFileName) {
			throw new InvalidConfigException;
		}

		$this->basePath = dirname(realpath($this->configFileName)) . "/";

		$fullPath = Util::fullDirectoryPath( $this->getBasePath(), $this->configFileName );
		$jsonConfigValid = Util::jsonFileContentIsValid( $fullPath );
		if (true !== $jsonConfigValid['success']) {
			echo $jsonConfigValid['message'] . "\n";
			throw new InvalidConfigException;
		}

		$this->config = json_decode(file_get_contents($this->configFileName), true);
		if (isset($this->config['emit']) && is_array($this->config['emit'])) {
			$this->emitList = $this->config['emit'];
		}

		if ($this->processes > 1) {
			$this->preferredTable = self::SQLITE_SYMBOL_TABLE;
		}

		if ($this->preferredTable == self::SQLITE_SYMBOL_TABLE) {
			if (!file_exists($this->getSymbolTableFile())) {
				$this->forceIndex = true;
			}
			if ($this->forceIndex && file_exists($this->getSymbolTableFile())) {
				unlink($this->getSymbolTableFile());
			}

			$this->symbolTable = new \BambooHR\Guardrail\SymbolTable\SqliteSymbolTable( $this->getSymbolTableFile(), $this->getBasePath() );
		} else {
			$this->forceIndex = true;
			$this->symbolTable = new \BambooHR\Guardrail\SymbolTable\InMemorySymbolTable( $this->getBasePath() );
		}

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
				$fullPath = Util::fullDirectoryPath( $this->basePath, $fileName );
				$function = require $fullPath;
				$plugins[] = call_user_func( $function, $index, $output );
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
				case '-p':
					$params = [];
					if ($argCount + 1 >= count($argv) || !preg_match('/^([0-9]+)\\/([0-9]+)$/', $argv[$argCount + 1], $params) ) {
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
					$this->fileList = [ $argv[++$argCount] ];
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
					$this->filter = UnifiedDiffFilter::importFile(
						realpath( $this->filterFileName )
					);
					$this->filter->display();
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
	 * getSymbolTableFile
	 *
	 * @return string
	 */
	private function getSymbolTableFile() {
		return $this->basePath . "/" . $this->symbolTableFile;
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
		return $this->outputFile;
	}
}