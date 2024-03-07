<?php namespace BambooHR\Guardrail\Abstractions;

/**
 * Guardrail.  Copyright (c) 2016-2023, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Config;
use BambooHR\Guardrail\Util;
use PhpParser\Node\Attribute;
use PhpParser\Node\ComplexType;
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

	public function getComplexReturnType() {
		return $this->method->returnType;
	}

	/**
	 * @return bool
	 */
	public function hasNullableReturnType() {
		return $this->method->returnType && $this->method->returnType instanceof NullableType;
	}

	public function getThrowsList():array {
		return $this->method->getAttribute('throws',[]);
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
		return false;
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
			$type = null;
			if (Config::shouldUseDocBlockForParameters()) {
				$type=$param->getAttribute('DocBlockName');
				if ($type && !$type->getAttribute('templates',false)) {
					$type = null;
				}
			}
			if (!$type) {
				$type = $param->type;
				if (!$type && Config::shouldUseDocBlockForParameters()) {
					$type = $param->getAttribute('DocBlockName');
				}
			}

			$ret[] = new FunctionLikeParameter(
				$type,
				$param->var->name,
				$param->default != null || $param->variadic,
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

	public function getAttributes(string $name): array {
		$ret=[];
		foreach($this->method->attrGroups as $group) {
			foreach($group->attrs as $attr) {
				/** @var Attribute $attr */
				if (strcasecmp($attr->name, $name)==0) {
					$ret[]=$attr;
				}
			}
		}
		return $ret;
	}
}