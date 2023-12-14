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
	protected $config;
	public $symbolTable;
	public $emitList;
	public $forceIndex;

	/**
	 * TestConfig constructor.
	 *
	 * @param string $file
	 * @param mixed  $emit
	 * @param array  $additionalConfig
	 */
	public function __construct($file, $emit, array $additionalConfig = []) {
		$this->basePath = $additionalConfig['basePath'] ?? dirname(realpath($file)) . "/";
		$this->config = array_merge([
			'options' => [
				"DocBlockReturns" => true,
				"DocBlockParams" => true,
				"DocBlockInlineVars" => true,
				"DocBlockProperties" => true,
			],
			'test' => [$file],
			'index' => [dirname($file)],
		], $additionalConfig);
		$this->loadConfigVars();
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

	/**
	 * @return array
	 */
	public function getPsrRoots() {
		// Method has to be overridden by $config is private in the parent class.
		if (isset($this->config) && array_key_exists('psr-roots', $this->config) && is_array($this->config['psr-roots'])) {
			return $this->config['psr-roots'];
		}

		return [];
	}
}
