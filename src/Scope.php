<?php namespace BambooHR\Guardrail;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use PhpParser\Node\FunctionLike;

/**
 * Class Scope
 *
 * @package BambooHR\Guardrail
 */
class Scope {
	const UNDEFINED = "!0";
	const MIXED_TYPE = "!1";
	const SCALAR_TYPE = "!2";

	/**
	 * @var array
	 */
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
    
    /**
     * Scope constructor.
     *
     * @param bool         $isStatic Set static
     * @param bool         $isGlobal Set global
     * @param FunctionLike $inside   Instance of FunctionLike (or null)
     */
	function __construct($isStatic, $isGlobal = false, FunctionLike $inside = null) {
		$this->isStatic = $isStatic;
		$this->isGlobal = $isGlobal;
		$this->inside = $inside;
	}

	/**
	 * isStatic
	 *
	 * @return bool
	 */
	public function isStatic() {
		return $this->isStatic;
	}

	/**
	 * isGlobal
	 *
	 * @return bool
	 */
	public function isGlobal() {
		return $this->isGlobal;
	}

	/**
	 * getInsideFunction
	 *
	 * @return FunctionLike
	 */
	public function getInsideFunction() {
		return $this->inside;
	}

	/**
	 * setVarType
	 *
	 * @param string $name The name
	 * @param string $type The type
	 *
	 * @return void
	 */
	public function setVarType($name, $type) {
		$this->vars[$name] = $type;
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

    /**
     * getVarType
     *
     * @param string $name The name
     *
     * @return mixed|string
     */
	function getVarType($name) {
		if (isset($this->vars[$name])) {
			return $this->vars[$name];
		}
		return isset($this->vars[$name]) ? $this->vars[$name] : self::UNDEFINED;
	}

	/**
	 * getScopeClone
	 *
	 * @return Scope
	 */
	public function getScopeClone() {
		$ret = new Scope($this->isStatic, $this->isGlobal);
		$ret->vars = $this->vars;
		$ret->written = $this->written;
		$ret->line = $this->line;
		return $ret;
	}
}