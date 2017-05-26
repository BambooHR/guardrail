<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\Abstractions;


use BambooHR\Guardrail\Abstractions\FunctionLikeParameter;
use BambooHR\Guardrail\Abstractions\MethodInterface;

class ReflectedClassMethod implements MethodInterface {
	private $refl;

	function __construct(\ReflectionMethod $refl) {
		$this->refl = $refl;
	}

	function isStatic() {
		return $this->refl->isStatic();
	}

	function isDeprecated() {
		return $this->refl->isDeprecated();
	}

	function isInternal() {
		return true;
	}

	function getReturnType() {
		return "";
	}

	function getDocBlockReturnType() {
		return "";
	}

	function isAbstract() {
		return $this->refl->isAbstract();
	}

	function getAccessLevel() {
		if ($this->refl->isPrivate()) return "private";
		if ($this->refl->isPublic()) return "public";
		if ($this->refl->isProtected()) return "protected";
	}

	function getMinimumRequiredParameters() {
		return $this->refl->getNumberOfRequiredParameters();
	}


	function getParameters() {
		$ret = [];
		$params = $this->refl->getParameters();
		/** @var \ReflectionParameter $param */
		foreach ($params as $param) {
			$type = $param->getClass() ? $param->getClass()->name : '';
			$ret[] = new FunctionLikeParameter( $type, $param->name, $param->isOptional(), $param->isPassedByReference());
		}
		return $ret;
	}

	function getName() {
		return $this->refl->getName();
	}

	function getStartingLine() {
		return 0;
	}

	function isVariadic() {
		if (method_exists($this->refl, "isVariadic")) {
			return $this->refl->isVariadic();
		} else {
			return true; // We assume internal functions are variadic so that we don't get bombarded with warnings.
		}
	}
}