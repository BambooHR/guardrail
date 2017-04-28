<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\SymbolTable;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Trait_;
use BambooHR\Guardrail\SymbolTable\SymbolTable;

class InMemorySymbolTable extends SymbolTable {
	private $classes = [];
	private $functions = [];
	private $interfaces = [];
	private $traits = [];

	function addFunction($name, Function_ $function, $file) {
		$this->functions[strtolower($name)]=$file;
	}

	function addClass($name, Class_ $class, $file) {
		$this->classes[strtolower($name)]= $file;
	}

	function addInterface($name, Interface_ $interface, $file) {
		$this->interfaces[strtolower($name)]=$file;
	}

	function addTrait($name, Trait_ $trait, $file) {
		$this->traits[strtolower($name)]=$file;
	}

	function addDefine($name, Node $define, $file) {
		$this->defines[strtolower($name)]=$file;
	}

	function getDefineFile($name) {
		return $this->defines[strtolower($name)];
	}

	function getTraitFile($name) {
		return $this->traits[strtolower($name)];
	}

	function getInterfaceFile($name) {
		return $this->interfaces[strtolower($name)];
	}

	function getClassFile($name) {
		return $this->classes[strtolower($name)];
	}

	function getFunctionFile($name) {
		return $this->functions[strtolower($name)];
	}

	function removeFileFromIndex($name) {
		self::removeFileFromOneIndex($this->traits, $name);
		self::removeFileFromOneIndex($this->interfaces, $name);
		self::removeFileFromOneIndex($this->functions, $name);
		self::removeFileFromOneIndex($this->classes,$name);
	}

	private static function removeFileFromOneIndex(&$index, $name) {
		foreach($index as $key=>$value) {
			if($value==$name) {
				unset($index[$key]);
			}
		}
	}


}
