<?php namespace BambooHR\Guardrail\Abstractions;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Util;
use PhpParser\Node\Expr\ConstFetch;
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
	public function getReturnType():string {
		return $this->method->returnType instanceof NullableType ? strval($this->method->returnType->type) : strval($this->method->returnType);
	}

	/**
	 * @return bool
	 */
	public function hasNullableReturnType():bool {
		return $this->method->returnType && $this->method->returnType instanceof NullableType;
	}

	/**
	 * isDeprecated
	 *
	 * @return bool
	 */
	public function isDeprecated():bool {
		$docBlock = $this->method->getDocComment();
		return (strpos($docBlock, "@deprecated") !== false);
	}

	/**
	 * getDocBlockReturnType
	 *
	 * @return string|null
	 */
	public function getDocBlockReturnType():?string {
		return $this->method->getAttribute('namespacedReturn');
	}

	/**
	 * getMinimumRequiredParameters
	 *
	 * @return int
	 */
	public function getMinimumRequiredParameters():int {
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
	public function getParameters():array {
		$ret = [];
		/** @var \PhpParser\Node\Param $param */
		foreach ($this->method->params as $param) {
			$ret[] = new FunctionLikeParameter(
				$param->type instanceof NullableType ? $param->type->type : $param->type,
				$param->var->name,
				$param->default != null,
				$param->byRef,
				$param->type instanceof NullableType || ($param->default instanceof ConstFetch && strcasecmp($param->default->name, "null") == 0)
			);
		}
		return $ret;
	}

	/**
	 * getAccessLevel
	 *
	 * @return string
	 */
	public function getAccessLevel():string {
		return Util::getMethodAccessLevel($this->method);
	}

	/**
	 * isInternal
	 *
	 * @return bool
	 */
	public function isInternal():bool {
		return false;
	}

	/**
	 * isAbstract
	 *
	 * @return bool
	 */
	public function isAbstract():bool {
		return $this->method->isAbstract();
	}

	/**
	 * isStatic
	 *
	 * @return bool
	 */
	public function isStatic():bool {
		return $this->method->isStatic();
	}

	/**
	 * getName
	 *
	 * @return string
	 */
	public function getName():string {
		return $this->method->name->name;
	}

	/**
	 * getStartingLine
	 *
	 * @return int
	 */
	public function getStartingLine():int {
		return $this->method->getLine();
	}

	/**
	 * isVariadic
	 *
	 * @return bool
	 */
	public function isVariadic():bool {
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