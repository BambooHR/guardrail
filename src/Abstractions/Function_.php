<?php namespace BambooHR\Guardrail\Abstractions;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use PhpParser\Node\Stmt\Function_ as AstFunction;
use BambooHR\Guardrail\NodeVisitors\VariadicCheckVisitor;

/**
 * Class Function_
 *
 * @package BambooHR\Guardrail\Abstractions
 */
class Function_ implements FunctionLikeInterface {

	/**
	 * @var AstFunction
	 */
	private $function;

	/**
	 * Function_ constructor.
	 *
	 * @param AstFunction $method Instance of AstFunction
	 */
	public function __construct(AstFunction $method) {
		$this->function = $method;
	}

	/**
	 * getReturnType
	 *
	 * @return string
	 */
	public function getReturnType() {
		return strval($this->function->returnType);
	}

	/**
	 * isDeprecated
	 *
	 * @return bool
	 */
	public function isDeprecated() {
		$docBlock = $this->function->getDocComment();
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
		return $this->function->getAttribute('namespacedReturn');
	}

	/**
	 * getMinimumRequiredParameters
	 *
	 * @return int
	 */
	public function getMinimumRequiredParameters() {
		$minimumArgs = 0;
		foreach ($this->function->params as $param) {
			if ($param->default) {
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
		foreach ($this->function->params as $param) {
			$ret[] = new FunctionLikeParameter($param->type, $param->name, $param->default != null, $param->byRef);
		}
		return $ret;
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
	 * getName
	 *
	 * @return string
	 */
	public function getName() {
		return $this->function->name;
	}

	/**
	 * getStartingLine
	 *
	 * @return int
	 */
	public function getStartingLine() {
		return $this->function->getLine();
	}

	/**
	 * isVariadic
	 *
	 * @return bool
	 */
	public function isVariadic() {
		foreach ($this->function->getParams() as $param) {
			if ($param->variadic) {
				return true;
			}
		}
		if ($this->function instanceof Function_ || $this->function instanceof \PhpParser\Node\Stmt\ClassMethod) {
			return VariadicCheckVisitor::isVariadic($this->function->getStmts());
		}
		return false;
	}
}