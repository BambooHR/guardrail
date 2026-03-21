<?php

namespace BambooHR\Guardrail\SymbolTable;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
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
	 * @var array
	 */
	private $defines = [];

	/** @var ClassLike[] */
	private $classNodes = [];

	/** @var Interface_[] */
	private $interfaceNodes = [];

	/** @var Trait_[] */
	private $traitNodes = [];

	/**
	 * addFunction
	 *
	 * @param string    $name     The name
	 * @param Function_ $function Instance of FunctionAbstraction
	 * @param string    $file     The file
	 *
	 * @return void
	 */
	public function addFunction($name, Function_ $function, $file) {
		$this->functions[strtolower($name)] = $this->basePath . '/' . $file;
	}

	/**
	 * addClass
	 *
	 * @param string $name  The name
	 * @param Class_ $class Instance of ClassAbstraction
	 * @param string $file  The file
	 *
	 * @return void
	 */
	public function addClass($name, ClassLike $class, $file) {
		$this->classes[strtolower($name)] = $this->basePath . '/' . $file;
		$this->classNodes[strtolower($name)] = $class;
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
		$this->interfaces[strtolower($name)] = $this->basePath . '/' . $file;
		$this->interfaceNodes[strtolower($name)] = $interface;
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
		$this->traits[strtolower($name)] = $this->basePath . '/' . $file;
		$this->traitNodes[strtolower($name)] = $trait;
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
		$this->defines[strtolower($name)] = $this->basePath . '/' . $file;
	}

	/**
	 * getDefineFile
	 *
	 * @param string $defineName The name
	 *
	 * @return mixed
	 */
	public function getDefineFile($defineName) {
		return isset($this->defines[strtolower($defineName)]) ? $this->defines[strtolower($defineName)] : null;
	}

	/**
	 * updateClass
	 *
	 * @param ClassLike $class Instance of ClassLike
	 *
	 * @return void
	 */
	public function updateClass(ClassLike $class) {
		// We don't store the class, so we don't need to update it.
	}


	/**
	 * getTraitFile
	 *
	 * @param string $name The name
	 *
	 * @return mixed
	 */
	public function getTraitFile($name) {
		if (!array_key_exists(strtolower($name), $this->traits)) {
			return null;
		}
		return $this->traits[strtolower($name)];
	}

	/**
	 * getInterfaceFile
	 *
	 * @param string $interfaceName The name
	 *
	 * @return mixed
	 */
	public function getInterfaceFile($interfaceName) {
		if (!isset($this->interfaces[strtolower($interfaceName)])) {
			return "";
		}
		return $this->interfaces[strtolower($interfaceName)];
	}

	/**
	 * getClassFile
	 *
	 * @param string $className The name
	 *
	 * @return mixed
	 */
	public function getClassFile($className) {
		if (!isset($this->classes[strtolower($className)])) {
			return "";
		}
		return $this->classes[strtolower($className)];
	}

	/**
	 * getFunctionFile
	 *
	 * @param string $methodName The name
	 *
	 * @return mixed
	 */
	public function getFunctionFile($methodName) {
		if (isset($this->functions[strtolower($methodName)])) {
			return $this->functions[strtolower($methodName)];
		}
		return "";
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

	/**
	 * getClassesThatUseAnyTrait
	 *
	 * @return array
	 */
	public function getClassesThatUseAnyTrait() {
		// grab anything that has a trait
		$return = [];
		foreach ($this->classes as $class) {
			// loop through each class
		}
		return $return;
	}

	/**
	 * isDefinedClass
	 *
	 * More efficient than getAbstractedClass, for the cases where you don't need the class.
	 *
	 * @param string $name The name
	 *
	 * @return bool
	 */
	public function getClass($name) {
		$cacheName = strtolower($name);
		if (isset($this->classNodes[$cacheName])) {
			return $this->classNodes[$cacheName];
		}
		return parent::getClass($name);
	}

	public function getInterface($name) {
		$cacheName = strtolower($name);
		if (isset($this->interfaceNodes[$cacheName])) {
			return $this->interfaceNodes[$cacheName];
		}
		return parent::getInterface($name);
	}

	public function getTrait($name) {
		$cacheName = strtolower($name);
		if (isset($this->traitNodes[$cacheName])) {
			return $this->traitNodes[$cacheName];
		}
		return parent::getTrait($name);
	}

	public function isDefinedClass($name) {
		$class = $this->getAbstractedClass($name);
		if ($class) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * classExistsAnyNamespace
	 *
	 * @param string $name The class name
	 *
	 * @return bool
	 */
	public function classExistsAnyNamespace($name) {
		return $this->classExistsInNamespace($this->classes, $name);
	}
}
