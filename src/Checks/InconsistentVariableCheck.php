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
 * Class InconsistentVariableCheck
 * 
 * Detects variables that may be unset due to inconsistent definition
 * across different control flow paths. Uses flow-sensitive type narrowing
 * to track which variables might not be defined at a given point.
 *
 * @package BambooHR\Guardrail\Checks
 */
class InconsistentVariableCheck extends BaseCheck {
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
	public function run($fileName, Node $node, ?ClassLike $inside = null, ?Scope $scope = null) {
		if ($node instanceof Variable) {
			// Skip variable variables
			if ($node->name instanceof Expr) {
				return;
			}
			
			// Only check string variable names in non-global scopes
			if (gettype($node->name) == 'string' && $scope && !$scope->isGlobal()) {
				$name = $node->name;
				$parentNodes = $scope->getParentNodes();

				// Skip special variables and closure use variables
				if (in_array($name, ["this", ...Util::getPhpGlobalNames()]) || 
				    ($parentNodes[count($parentNodes) - 1] instanceof Expr\ClosureUse)) {
					return;
				}
				
				// Skip if this is an assignment (left-hand side)
				if ($node->hasAttribute('assignment')) {
					return;
				}
				
				// Skip if variable is used inside isset(), empty(), or array_key_exists()
				// These functions are specifically designed to handle potentially unset variables
				$parent = $parentNodes[count($parentNodes) - 1] ?? null;
				
				// Check for isset() and empty() language constructs
				if ($parent instanceof Node\Expr\Isset_ || $parent instanceof Node\Expr\Empty_) {
					return;
				}
				
				// Check for array_key_exists() function call
				if ($parent instanceof Node\Arg) {
					$grandparent = $parentNodes[count($parentNodes) - 2] ?? null;
					if ($grandparent instanceof Node\Expr\FuncCall && $grandparent->name instanceof Node\Name) {
						$funcName = strtolower($grandparent->name->toString());
						if ($funcName === 'array_key_exists') {
							return;
						}
					}
				}
				
				// Get the variable object from scope
				$var = $scope->getVarObject($name);
				
				// If variable exists and may be unset, emit error
				if ($var && $var->mayBeUnset) {
					$this->emitError(
						$fileName, 
						$node, 
						ErrorConstants::TYPE_INCONSISTENT_VARIABLE, 
						"Variable \$$name may not be defined in all code paths"
					);
				}
			}
		}
	}
}
