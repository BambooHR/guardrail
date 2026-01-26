<?php namespace BambooHR\Guardrail\Abstractions;

/**
 * Guardrail.  Copyright (c) 2016-2023, JBambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Config;
use BambooHR\Guardrail\NodeVisitors\VariadicCheckVisitor;
use BambooHR\Guardrail\TypeComparer;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Function_;
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
	 * @return FunctionLikeParameter[]
	 */
	public function getParameters() {
		$ret = array_map(
			fn($param) => new FunctionLikeParameter(
				self::resolveDeclaredParamTypes($param),
				$param->var->name,
				$param->variadic || $param->default != null,
				$param->byRef,
				(
					$param->type instanceof NullableType ||
					(
						$param->default instanceof ConstFetch &&
						strcasecmp($param->default->name, "null") == 0
					)
				)
			),
			$this->function->params
		);
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
		if ($this->function->getAttribute("variadic_implementation")) {
			return true;
		}

		return false;
	}

	public function getThrowsList():array {
		return $this->function->getAttribute('throws', []);
	}

	/**
	 * @param mixed $param
	 * @return mixed|Name
	 */
	static function resolveDeclaredParamTypes(Param $param): mixed {
		$docBlockType = $param->getAttribute('DocBlockName');
		if (
			Config::shouldUseDocBlockGenerics() &&
			$docBlockType instanceof Name &&
			(
				strcasecmp($docBlockType, "T") == 0 ||
				strcasecmp($docBlockType, "class-string") == 0
			)
		) {
			return $docBlockType;
		} else if (Config::shouldUseDocBlockForParameters()) {
			$type = $param->type;
			if (!$type) {
				// A parameter can not be only null.  So disregard these.
				if ($docBlockType && !TypeComparer::isNamedIdentifier($docBlockType, "null")) {
					return $docBlockType;
				}
			}
			return $type;
		}
		return $param->type;
	}
}