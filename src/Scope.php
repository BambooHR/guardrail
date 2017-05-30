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

	/**
	 * Scope constructor.
	 *
	 * @param bool         $isStatic Set static
	 * @param bool         $isGlobal Set global
	 * @param FunctionLike $inside   Instance of FunctionLike (or null)
	 */
	public function __construct($isStatic, $isGlobal = false, FunctionLike $inside = null) {
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

	/**
	 * getVarType
	 *
	 * @param string $name The name
	 *
	 * @return mixed|string
	 */
	public function getVarType($name) {
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
		return $ret;
	}
}