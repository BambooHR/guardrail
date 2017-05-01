<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail;

use BambooHR\Guardrail\Checks\BaseCheck;
use BambooHR\Guardrail\Exceptions\InvalidConfigException;
use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\SymbolTable\SymbolTable;

class Config {
	const MEMORY_SYMBOL_TABLE=1;
	const SQLITE_SYMBOL_TABLE=2;

	/** @var int Number of analyzer processes to run.  If 1 then we don't run a child process.  */
	private $processes=1;

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
	private $partitions=1;

	/** @var int Which partition this server is running  */
	private $partitionNumber=1;

	/** @var string  */
	private $outputFile = "";

	/** @var int MEMORY_SYMBOL_TABLE | SQLITE_SYMBOL_TABLE */
	private $preferredTable = self::MEMORY_SYMBOL_TABLE;

	/** @var \BambooHR\Guardrail\SymbolTable\SymbolTable */
	private $symbolTable = null;

	/** @var string[]|false The list of files to process */
	private $fileList=false;


	/** @var bool  */
	private $forceIndex=false;

	/** @var bool  */
	private $forceAnalysis=false;

	/** @var string */
	private $configFileName = "";

	/** @var string[] */
	private $emitList = [];

	/** @var int  */
	private $outputLevel = 0;

	/**
	 * @param string $file File to import.
	 */
	function __construct($argv) {
		if(count($argv)<2) {
			throw new InvalidConfigException;
		}

		$this->parseArgv($argv);

		if(!$this->configFileName) {
			throw new InvalidConfigException;
		}

		$this->basePath=dirname(realpath($this->configFileName))."/";
		$this->config=json_decode(file_get_contents($this->configFileName),true);
		if(isset($this->config['emit']) && is_array($this->config['emit'])) {
			$this->emitList = $this->config['emit'];
		}

		if($this->processes>1) {
			$this->preferredTable = self::SQLITE_SYMBOL_TABLE;
		}

		if($this->preferredTable==self::SQLITE_SYMBOL_TABLE) {
			if(!file_exists($this->getSymbolTableFile())) {
				$this->forceIndex = true;
			}
			if($this->forceIndex && file_exists($this->getSymbolTableFile())) {
				unlink($this->getSymbolTableFile());
			}

			$this->symbolTable = new \BambooHR\Guardrail\SymbolTable\SqliteSymbolTable( $this->getSymbolTableFile(), $this->getBasePath() );
		} else {
			$this->forceIndex=true;
			$this->symbolTable = new \BambooHR\Guardrail\SymbolTable\InMemorySymbolTable( $this->getBasePath() );
		}

	}

	/**
	 * @var SymbolTable     $index
	 * @var OutputInterface $output
	 * @return BaseCheck[]
	 */
	function getPlugins(SymbolTable $index, OutputInterface $output) {
		$plugins = [];
		if(isset($this->config['plugins']) && is_array($this->config['plugins'])) {
			foreach($this->config['plugins'] as $fileName) {
				$fullPath = strpos($fileName,DIRECTORY_SEPARATOR)===0 ?
					$fileName :
					$this->basePath . DIRECTORY_SEPARATOR . $fileName;
				$function = require $fullPath;
				$plugins[] = call_user_func( $function, $index, $output );
			}
		}
		return $plugins;
	}

	/**
	 * @return int
	 */
	function getOutputLevel() {
		return $this->outputLevel;
	}

	/**
	 * @param array $argv
	 * @return array
	 * @throws InvalidConfigException
	 */
	private function parseArgv(array $argv) {
		$nextArg=0;
		for($i=1;$i<count($argv);++$i) {
			switch ($argv[$i]) {
				case '-a':
					$this->forceAnalysis=true;
					break;
				case '-i':
					$this->forceIndex=true;
					break;
				case '-s':
					$this->preferredTable=self::SQLITE_SYMBOL_TABLE;
					break;
				case '-m':
					$this->preferredTable=self::MEMORY_SYMBOL_TABLE;
					break;
				case '-p':
					$params = [];
					if ($i+1 >= count($argv) || !preg_match('/^([0-9]+)\\/([0-9]+)$/', $argv[$i+1], $params) ) {
						throw new InvalidConfigException;
					}
					++$i;
					list($wholeMatch, $this->partitionNumber, $this->partitions) = $params;
					if($this->partitionNumber<1 || $this->partitionNumber>$this->partitions) {
						throw new InvalidConfigException;
					}
					break;
				case '-v':
					$this->outputLevel++;
					break;
				case '-n':
					if ($i + 1 >= count($argv)) throw new InvalidConfigException;
					$this->processes = intval($argv[++$i]);
					break;
				case '-f':
					if ($i + 1 >= count($argv)) throw new InvalidConfigException;
					$this->preferredTable=self::SQLITE_SYMBOL_TABLE;
					$this->fileList=[ $argv[++$i] ];
					$this->reindex = true;
					break;
				case '-o':
					if ($i + 1 >= count($argv)) throw new InvalidConfigException;
					$this->outputFile = $argv[++$i];
					break;
				case '-h':
				case '--help':
					throw new InvalidConfigException;
					break;
				default:
					switch($nextArg) {
						case 0:
							$this->configFileName = $argv[$i];
							break;
						case 1:
							$this->fileList = explode("\n", file_get_contents($argv[$i]));
							break;
						default:
							throw new InvalidConfigException;
					}
					$nextArg++;
			}
		}
		if($this->preferredTable==self::MEMORY_SYMBOL_TABLE) {
			$this->forceIndex = true;
		}
	}

	function getProcessCount() {
		return $this->processes;
	}

	function getConfigArray() {
		return $this->config;
	}

	function hasFileList() {
		return $this->fileList !== false;
	}

	function getFileList() {
		return $this->fileList;
	}

	function getConfigFileName() {
		return $this->configFileName;
	}

	function getPartitions() {
		return $this->partitions;
	}

	function getPartitionNumber() {
		return $this->partitionNumber;
	}

	function getBasePath() {
		return $this->basePath;
	}

	function getSymbolTable() {
		return $this->symbolTable;
	}

	function shouldIndex() {
		return $this->forceIndex;
	}

	function shouldAnalyze() {
		return $this->forceAnalysis;
	}

	private function getSymbolTableFile() {
		return $this->basePath."/".$this->symbolTableFile;
	}

	function shouldReindex() {
		return $this->reindex;
	}

	function getEmitList() {
		return $this->emitList;
	}

	function processCount() {
		return $this->processes;
	}

	function getOutputFile() {
		return $this->outputFile;
	}
}