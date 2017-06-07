<?php namespace BambooHR\Guardrail\Tests;

use BambooHR\Guardrail\Config;
use BambooHR\Guardrail\SymbolTable\InMemorySymbolTable;
use BambooHR\Guardrail\SymbolTable\SymbolTable;

/**
 * Class TestConfig
 *
 * @package BambooHR\Guardrail\Tests
 */
class TestConfig extends Config {

	public $basePath;

	public $config;

	public $symbolTable;

	public $emitList;

	public $forceIndex;

	/**
	 * TestConfig constructor.
	 *
	 * @param string $file
	 */
	public function __construct($file, $emit) {
		$this->basePath = dirname(realpath($file)) . "/";
		$this->config = [
			'test' => [$file],
			'index' => [dirname($file)],
		];
		$this->forceIndex = true;
		$this->symbolTable = new InMemorySymbolTable($this->basePath);
		if (!is_array($emit)) {
			$emit = [$emit];
		}
		$this->emitList = $emit;
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
	 * getEmitList
	 *
	 * @return mixed|\string[]
	 */
	public function getEmitList() {
		return $this->emitList;
	}

}