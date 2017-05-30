<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail;


use PhpParser\Node\FunctionLike;

class Scope {
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

	function __construct($isStatic, $isGlobal = false, FunctionLike $inside = null) {
		$this->isStatic = $isStatic;
		$this->isGlobal = $isGlobal;
		$this->inside = $inside;
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
		$this->vars[$name] = $type;
	}

	function getVarType($name) {
		if (isset($this->vars[$name])) {
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
		return $ret;
	}
}