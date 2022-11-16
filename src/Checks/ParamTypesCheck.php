<?php namespace BambooHR\Guardrail\Checks;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\NodeVisitors\ForEachNode;
use BambooHR\Guardrail\Util;
use PhpParser\Node;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use BambooHR\Guardrail\Scope;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\UnionType;

/**
 * Class ParamTypesCheck
 *
 * @package BambooHR\Guardrail\Checks
 */
class ParamTypesCheck extends BaseCheck {

	/**
	 * getCheckNodeTypes
	 *
	 * @return array
	 */
	public function getCheckNodeTypes() {
		return [ ClassMethod::class, Function_::class, Closure::class];
	}

	/**
	 * isAllowed
	 *
	 * @param string         $name   The name
	 * @param ClassLike|null $inside Instance of ClassLike | null
	 *
	 * @return bool
	 */
	protected function isAllowed($name, ClassLike $inside=null) {
		$nameLower = strtolower($name);
		if ($nameLower == "self" && $inside instanceof Class_) {
			return true;
		}
		if ($nameLower != "" && !Util::isLegalNonObject($name)) {
			$class = $this->symbolTable->isDefinedClass($name);
			if (!$class && !$this->symbolTable->ignoreType($name)) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @param Node\NullableType|Node\Name $type The name of the parameter
	 * @return string
	 */
	private function getNullableTypeName($type) {
		$type = $type instanceof Node\NullableType ? $type->type : $type;
		return $type instanceof UnionType ? 'mixed' : strval($type);
	}

	/**
	 * run
	 *
	 * @param string         $fileName The name of the file we are parsing
	 * @param Node           $node     Instance of the Node
	 * @param ClassLike|null $inside   Instance of the ClassLike (the class we are parsing) [optional]
	 * @param Scope|null     $scope    Instance of the Scope (all variables in the current state) [optional]
	 *
	 * @return void
	 */
	public function run($fileName, Node $node, ClassLike $inside=null, Scope $scope=null) {

		if ($node instanceof Function_) {
			$this->checkForNestedFunction($fileName, $node, $inside, $scope);
		}

		if ($node instanceof Function_) {
			$displayName = $node->name;
		} else if ($node instanceof ClassMethod) {
			$displayName = $node->name;
		} else {
			$displayName = "closure function";
		}

		if ($node instanceof Node\FunctionLike) {
			foreach ($node->getParams() as $index => $param) {

				if ($param->type) {
					$name = $this->getNullableTypeName($param->type);
					if (!$this->isAllowed($name, $inside)) {
						$this->emitError($fileName, $node, ErrorConstants::TYPE_UNKNOWN_CLASS, "Reference to an unknown type '$name'' in parameter $index of $displayName");
					}
				}
			}

			if ($node->getReturnType()) {
				$returnType = $this->getNullableTypeName( $node->getReturnType() );
				if (!$this->isAllowed($returnType, $inside)) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_UNKNOWN_CLASS, "Reference to an unknown type '$returnType' in return value of $displayName");
				}
			}
		}
	}

	/**
	 * checkForNestedFunction
	 *
	 * @param string         $fileName The name of the file we are parsing
	 * @param Node           $node     Instance of the Node
	 * @param ClassLike|null $inside   Instance of the ClassLike (the class we are parsing) [optional]
	 * @param Scope|null     $scope    Instance of the Scope (all variables in the current state) [optional]
	 *
	 * @return void
	 */
	public function checkForNestedFunction($fileName, Node\FunctionLike $node, ClassLike $inside = null, Scope $scope = null) {
		$self = $this;
		ForEachNode::run( $node->getStmts(), function($statement) use ($self, $fileName, $node) {
			if ($statement instanceof Node\Stmt\Function_) {
				$self->emitError($fileName, $node, ErrorConstants::TYPE_FUNCTION_INSIDE_FUNCTION, "Function declaration detected inside another function or method");
			}
		});
	}
}