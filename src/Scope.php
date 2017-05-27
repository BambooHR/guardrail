<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail;


use PhpParser\Node\FunctionLike;

class Scope
{
	const UNDEFINED = "!0";
	const MIXED_TYPE = "!1";
	const SCALAR_TYPE = "!2";

	private $vars = [];

	/** @var  bool */
	private $isStatic;

	/** @var  bool */
	private $isGlobal;

	/** @var FunctionLike */
	private $inside;

	/** @var bool[] */
	private $written = [];

	/** @var int[]  */
	private $line = [];

	function __construct($isStatic, $isGlobal = false, FunctionLike $inside = null) {
		$this->isStatic=$isStatic;
		$this->isGlobal=$isGlobal;
		$this->inside=$inside;
	}

	function isStatic() {
		return $this->isStatic;
	}

	function isGlobal() {
		return $this->isGlobal;
	}

	/**
	 * @return FunctionLike
	 */
	function getInsideFunction() {
		return $this->inside;
	}

	function setVarType($name, $type) {
		$this->vars[$name]=$type;
	}

	function setVarWritten($name, $line) {
		if(!isset($this->written[$name])) {
			$this->written[$name] = true;
			$this->line[$name] = $line;
		}
	}

	function setVarUsed($name) {
		$this->written[$name] = false;
	}

	function markAllVarsUsed() {
		foreach(array_keys($this->written) as $name) {
			$this->written[$name] = false;
		}
	}

	function getUnusedVars() {
		$ret = [];
		foreach($this->written as $key=>$unused) {
			if($unused) {
				$ret[$key]=$this->line[$key];
			}
		}
		return $ret;
	}

	function getVarType($name) {
		if(isset($this->vars[$name])) {
			return $this->vars[$name];
		}
		return isset($this->vars[$name]) ? $this->vars[$name] : self::UNDEFINED;
	}

	/**
	 * @return Scope
	 */
	function getScopeClone() {
		$ret = new Scope($this->isStatic, $this->isGlobal);
		$ret->vars = $this->vars;
		$ret->written = $this->written;
		$ret->line = $this->line;
		return $ret;
	}
}