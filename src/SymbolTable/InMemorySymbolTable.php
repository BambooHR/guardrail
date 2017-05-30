<?php namespace BambooHR\Guardrail\SymbolTable;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Trait_;

/**
 * Class InMemorySymbolTable
 *
 * @package BambooHR\Guardrail\SymbolTable
 */
class InMemorySymbolTable extends SymbolTable {

	/**
	 * @var array
	 */
	private $classes = [];

	/**
	 * @var array
	 */
	private $functions = [];

	/**
	 * @var array
	 */
	private $interfaces = [];

	/**
	 * @var array
	 */
	private $traits = [];

	/**
	 * addFunction
	 *
	 * @param string    $name     The name
	 * @param Function_ $function Instance of Function_
	 * @param string    $file     The file
	 *
	 * @return void
	 */
	public function addFunction($name, Function_ $function, $file) {
		$this->functions[strtolower($name)] = $file;
	}

	/**
	 * addClass
	 *
	 * @param string $name  The name
	 * @param Class_ $class Instance of Class_
	 * @param string $file  The file
	 *
	 * @return void
	 */
	public function addClass($name, Class_ $class, $file) {
		$this->classes[strtolower($name)] = $file;
	}

	/**
	 * addInterface
	 *
	 * @param string     $name      The name
	 * @param Interface_ $interface Instance of Interface
	 * @param string     $file      The file
	 *
	 * @return void
	 */
	public function addInterface($name, Interface_ $interface, $file) {
		$this->interfaces[strtolower($name)] = $file;
	}

	/**
	 * addTrait
	 *
	 * @param string $name  The name
	 * @param Trait_ $trait Instance of Trait_
	 * @param string $file  The file
	 *
	 * @return void
	 */
	public function addTrait($name, Trait_ $trait, $file) {
		$this->traits[strtolower($name)] = $file;
	}

	/**
	 * addDefine
	 *
	 * @param string $name   The name
	 * @param Node   $define Instance of Node
	 * @param string $file   The file
	 *
	 * @return void
	 */
	public function addDefine($name, Node $define, $file) {
		$this->defines[strtolower($name)] = $file;
	}

	/**
	 * getDefineFile
	 *
	 * @param string $name The name
	 *
	 * @return mixed
	 */
	public function getDefineFile($name) {
		return $this->defines[strtolower($name)];
	}

	/**
	 * getTraitFile
	 *
	 * @param string $name The name
	 *
	 * @return mixed
	 */
	public function getTraitFile($name) {
		return $this->traits[strtolower($name)];
	}

	/**
	 * getInterfaceFile
	 *
	 * @param string $name The name
	 *
	 * @return mixed
	 */
	public function getInterfaceFile($name) {
		return $this->interfaces[strtolower($name)];
	}

	/**
	 * getClassFile
	 *
	 * @param string $name The name
	 *
	 * @return mixed
	 */
	public function getClassFile($name) {
		return $this->classes[strtolower($name)];
	}

	/**
	 * getFunctionFile
	 *
	 * @param string $name The name
	 *
	 * @return mixed
	 */
	public function getFunctionFile($name) {
		return $this->functions[strtolower($name)];
	}

	/**
	 * removeFileFromIndex
	 *
	 * @param string $name The name
	 *
	 * @return void
	 */
	public function removeFileFromIndex($name) {
		self::removeFileFromOneIndex($this->traits, $name);
		self::removeFileFromOneIndex($this->interfaces, $name);
		self::removeFileFromOneIndex($this->functions, $name);
		self::removeFileFromOneIndex($this->classes, $name);
	}

	/**
	 * removeFileFromOneIndex
	 *
	 * @param string $index The index
	 * @param string $name  The name
	 *
	 * @return void
	 */
	private static function removeFileFromOneIndex(&$index, $name) {
		foreach ($index as $key => $value) {
			if ($value == $name) {
				unset($index[$key]);
			}
		}
	}

}
