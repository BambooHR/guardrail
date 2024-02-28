<?php namespace BambooHR\Guardrail\Abstractions;

/**
 * Guardrail.  Copyright (c) 2016-2023, JBambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\NodeVisitors\VariadicCheckVisitor;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\Function_ as AstFunction;

/**
 * Class FunctionAbstraction
 *
 * @package BambooHR\Guardrail\Abstractions
 */
class FunctionAbstraction implements FunctionLikeInterface {

	/**
	 * @var AstFunction
	 */
	private $function;

	/**
	 * FunctionAbstraction constructor.
	 *
	 * @param AstFunction $method Instance of AstFunction
	 */
	public function __construct(AstFunction $method) {
		$this->function = $method;
	}

	public function getComplexReturnType() {
		return $this->function->returnType;
	}

	/**
	 * @return bool
	 */
	public function hasNullableReturnType() {
		return $this->function->returnType instanceof NullableType;
	}


	/**
	 * isDeprecated
	 *
	 * @return bool
	 */
	public function isDeprecated() {
		$comment = $this->function->getDocComment();
		if ($comment) {
			return str_contains($comment->getText(), "@deprecated");
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
			$ret[] = new FunctionLikeParameter(
				$param->type,
				$param->var->name,
				$param->default != null,
				$param->byRef,
				$param->type instanceof NullableType || ($param->default instanceof ConstFetch && strcasecmp($param->default->name, "null") == 0)
			);
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
		if ($this->function instanceof FunctionAbstraction || $this->function instanceof \PhpParser\Node\Stmt\ClassMethod) {
			return VariadicCheckVisitor::isVariadic($this->function->getStmts());
		}
		return false;
	}

	public function getThrowsList():array {
		return $this->function->getAttribute('throws', []);
	}
}