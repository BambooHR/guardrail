<?php namespace BambooHR\Guardrail\Checks;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\ClassLike;
use BambooHR\Guardrail\Scope;

/**
 * Class UndefinedVariableCheck
 *
 * @package BambooHR\Guardrail\Checks
 */
class UndefinedVariableCheck extends BaseCheck {

	/**
	 * getCheckNodeTypes
	 *
	 * @return array
	 */
	public function getCheckNodeTypes() {
		return [Variable::class];
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
		if ($node instanceof Variable &&$node->name instanceof Expr) {
			$this->emitError($fileName, $node, ErrorConstants::TYPE_VARIABLE_VARIABLE, "Variable variable detected");
		} else if (gettype($node->name) == 'string' && $scope && !$scope->isGlobal()) {
			$name = $name = $node->name;
			if ($name != "GLOBALS" && $name != "_GET" && $name != "_POST" && $name != "_COOKIE" && $name != "_REQUEST" && $name != "this" && $name != "_SERVER" && $name != "_SESSION" && $name != "_FILES" && $name != "http_response_header") {
				$this->incTests();
				if ($scope->getVarType($name) == Scope::UNDEFINED) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_UNKNOWN_VARIABLE, "Undefined variable: $name");
				}
			}
		}
	}
}