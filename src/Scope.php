<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail;


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

	function __construct($isStatic, $isGlobal=false) {
		$this->isStatic=$isStatic;
		$this->isGlobal=$isGlobal;
	}

	function isStatic() {
		return $this->isStatic;
	}

	function isGlobal() {
		return $this->isGlobal;
	}

	function setVarType($name, $type) {
		$this->vars[$name]=$type;
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
		return $ret;
	}
}