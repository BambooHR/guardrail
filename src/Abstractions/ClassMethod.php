<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\Abstractions;

use BambooHR\Guardrail\Util;

class ClassMethod implements MethodInterface {
	private $method;

	function __construct(\PhpParser\Node\Stmt\ClassMethod $method) {
		$this->method = $method;
	}
	function getReturnType() {
		return strval($this->method->returnType);
	}

	function isDeprecated() {
		$docBlock = $this->method->getDocComment();
		if (strpos($docBlock, "@deprecated") !== false) {
			return true;
		}
	}

	function getDocBlockReturnType() {
		return $this->method->getAttribute('namespacedReturn');
	}

	function getMinimumRequiredParameters() {
		$minimumArgs = 0;
		foreach ($this->method->params as $param) {
			if ($param->default) break;
			$minimumArgs++;
		}
		return $minimumArgs;
	}

	/**
	 * @return FunctionLikeParameter[]
	 */
	function getParameters() {
		$ret = [];
		/** @var \PhpParser\Node\Param $param */
		foreach ($this->method->params as $param) {
			$ret[] = new FunctionLikeParameter($param->type, $param->name, $param->default != null, $param->byRef);
		}
		return $ret;
	}

	function getAccessLevel() {
		return Util::getMethodAccessLevel($this->method);
	}

	function isInternal() {
		return false;
	}

	function isAbstract() {
		return $this->method->isAbstract();
	}

	function isStatic() {
		return $this->method->isStatic();
	}

	function getName() {
		return $this->method->name;
	}

	function getStartingLine() {
		return $this->method->getLine();
	}

	function isVariadic() {
		foreach ($this->method->getParams() as $param) {
			if ($param->variadic) {
				return true;
			}
		}
		if ($this->method->getAttribute("variadic_implementation")) {
			return true;
		}
		return false;
	}
}