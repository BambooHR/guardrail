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
			if (gettype($node->name) == 'string' && $scope) {
				// Get the current scope from the stack (respects TypeAssertion narrowing)
				// The $scope parameter is actually a ScopeStack, not a Scope
				$scopeStack = $scope instanceof \BambooHR\Guardrail\Scope\ScopeStack 
					? $scope 
					: null;
				$currentScope = $scopeStack ? $scopeStack->getCurrentScope() : $scope;
				
				if ($currentScope->isGlobal()) {
					return;
				}
				
				$name = $node->name;
				$parentNodes = $scopeStack ? $scopeStack->getParentNodes() : [];

				// Skip special variables and closure use variables
				if (in_array($name, ["this", ...Util::getPhpGlobalNames()]) || 
				    ($parentNodes[count($parentNodes) - 1] instanceof Expr\ClosureUse)) {
					return;
				}
				
				// Skip if this is an assignment (left-hand side)
				if ($node->hasAttribute('assignment')) {
					return;
				}
				
				// Skip if variable is used inside isset(), empty(), array_key_exists(), or assert()
				// These functions are specifically designed to handle potentially unset variables
				$parent = $parentNodes[count($parentNodes) - 1] ?? null;
				
				// Check for isset() and empty() language constructs
				if ($parent instanceof Node\Expr\Isset_ || $parent instanceof Node\Expr\Empty_) {
					return;
				}
				
				// Check for array_key_exists() and assert() function calls
				// Need to check all parent nodes since variable might be inside an expression (e.g., assert($var instanceof Type))
				foreach ($parentNodes as $parentNode) {
					if ($parentNode instanceof Node\Expr\FuncCall && $parentNode->name instanceof Node\Name) {
						$funcName = strtolower($parentNode->name->toString());
						if ($funcName === 'array_key_exists' || $funcName === 'assert') {
							return;
						}
					}
				}
				
				// Get the variable object from current scope (respects TypeAssertion)
				$var = $currentScope->getVarObject($name);
				
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
