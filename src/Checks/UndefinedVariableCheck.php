<?php 

namespace BambooHR\Guardrail\Checks;

/**
 * Guardrail.  Copyright (c) 2016-2023, BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\Util;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\ClassLike;

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
	public function run($fileName, Node $node, ?ClassLike $inside=null, ?Scope $scope=null) {
		if ($node instanceof Variable) {
			if ($node->name instanceof Expr) {
				$this->emitError($fileName, $node, ErrorConstants::TYPE_VARIABLE_VARIABLE, "Variable variable detected");
			} else if (gettype($node->name) == 'string' && $scope && !$scope->isGlobal()) {
				$name = $node->name;
				$parentNodes = $scope->getParentNodes();

				if (!in_array($name, ["this", ...Util::getPhpGlobalNames()]) && !($parentNodes[count($parentNodes) - 1] instanceof Expr\ClosureUse)) {
					if (!$scope->getVarExists($name) && !$node->hasAttribute('assignment')) {
						$this->emitError($fileName, $node, ErrorConstants::TYPE_UNKNOWN_VARIABLE, "Undefined variable: $name");
					}
				}
			}
		}
	}
}