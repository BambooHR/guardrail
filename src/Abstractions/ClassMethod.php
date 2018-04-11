<?php namespace BambooHR\Guardrail\Abstractions;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Util;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\ClassMethod as ParserClassMethod;

/**
 * Class ClassMethod
 *
 * @package BambooHR\Guardrail\Abstractions
 */
class ClassMethod implements MethodInterface {

	/**
	 * @var ParserClassMethod
	 */
	private $method;

	/**
	 * ClassMethod constructor.
	 *
	 * @param ParserClassMethod $method Instance of ClassMethod
	 */
	public function __construct(ParserClassMethod $method) {
		$this->method = $method;
	}

	/**
	 * getReturnType
	 *
	 * @return string
	 */
	public function getReturnType() {
		return $this->method->returnType instanceof NullableType
			? strval($this->method->returnType->type)
			: strval($this->method->returnType);
	}

	/**
	 * @return bool
	 */
	public function hasNullableReturnType() {
		return $this->method->returnType && $this->method->returnType instanceof NullableType;
	}

	/**
	 * isDeprecated
	 *
	 * @return bool
	 */
	public function isDeprecated() {
		$docBlock = $this->method->getDocComment();
		if (strpos($docBlock, "@deprecated") !== false) {
			return true;
		}
	}

	/**
	 * getDocBlockReturnType
	 *
	 * @return mixed|null
	 */
	public function getDocBlockReturnType() {
		return $this->method->getAttribute('namespacedReturn');
	}

	/**
	 * getMinimumRequiredParameters
	 *
	 * @return int
	 */
	public function getMinimumRequiredParameters() {
		$minimumArgs = 0;
		foreach ($this->method->params as $param) {
			if ($param->default || $param->variadic) {
				break;
			}
			$minimumArgs++;
		}
		return $minimumArgs;
	}

	/**
	 * getParameters
	 *
	 * @return FunctionLikeParameter[]
	 */
	public function getParameters() {
		$ret = [];
		/** @var \PhpParser\Node\Param $param */
		foreach ($this->method->params as $param) {
			$ret[] = new FunctionLikeParameter(
				$param->type instanceof NullableType ? $param->type->type : $param->type,
				$param->name,
				$param->default != null,
				$param->byRef,
				$param->type instanceof NullableType
			);
		}
		return $ret;
	}

	/**
	 * getAccessLevel
	 *
	 * @return string
	 */
	public function getAccessLevel() {
		return Util::getMethodAccessLevel($this->method);
	}

	/**
	 * isInternal
	 *
	 * @return bool
	 */
	public function isInternal() {
		return false;
	}

	/**
	 * isAbstract
	 *
	 * @return bool
	 */
	public function isAbstract() {
		return $this->method->isAbstract();
	}

	/**
	 * isStatic
	 *
	 * @return bool
	 */
	public function isStatic() {
		return $this->method->isStatic();
	}

	/**
	 * getName
	 *
	 * @return string
	 */
	public function getName() {
		return $this->method->name;
	}

	/**
	 * getStartingLine
	 *
	 * @return int
	 */
	public function getStartingLine() {
		return $this->method->getLine();
	}

	/**
	 * isVariadic
	 *
	 * @return bool
	 */
	public function isVariadic() {
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