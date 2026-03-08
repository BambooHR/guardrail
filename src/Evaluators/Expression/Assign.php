<?php

namespace BambooHR\Guardrail\Evaluators\Expression;

use BambooHR\Guardrail\Config;
use BambooHR\Guardrail\Evaluators\ExpressionInterface;
use BambooHR\Guardrail\Evaluators\OnEnterEvaluatorInterface;
use BambooHR\Guardrail\Evaluators\OnExitEvaluatorInterface;
use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeComparer;
use PhpParser\Node;
use PhpParser\Node\Expr\List_;
use PhpParser\Node\Expr\Variable;

class Assign implements ExpressionInterface, OnEnterEvaluatorInterface
{
	function getInstanceType(): array|string {
		return [Node\Expr\Assign::class, Node\Expr\AssignRef::class, Node\Expr\List_::class];
	}

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): ?Node {
		if ($node instanceof List_) {
			return TypeComparer::identifierFromName("array");
		}

		/** @var Node\Expr\Assign $assign */
		$assign = $node;
		$valueType = $assign->expr->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);
		
		// Get mayBeNull flag from source expression
		$sourceMayBeNull = $this->getMayBeNullFromExpr($assign->expr, $scopeStack);
		
		$this->setValueType($assign->var, $valueType, $sourceMayBeNull, $scopeStack);
		return $valueType;
	}

	/**
	 * Get the mayBeNull flag from an expression
	 * 
	 * @param Node\Expr $expr The expression to check
	 * @param ScopeStack $scope The current scope stack
	 * @return bool True if the expression may be null
	 */
	function getMayBeNullFromExpr(Node\Expr $expr, ScopeStack $scope): bool {
		// Check if the type itself is nullable
		$type = $expr->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);
		if (TypeComparer::isTypeNullable($type)) {
			return true;
		}
		
		// If it's a variable, check its mayBeNull flag
		if ($expr instanceof Node\Expr\Variable && gettype($expr->name) == "string") {
			$currentScope = $scope->getCurrentScope();
			$var = $currentScope?->getVarObject($expr->name);
			if ($var && $var->mayBeNull) {
				return true;
			}
		}
		
		// If it's a property fetch, check the chain
		if ($expr instanceof Node\Expr\PropertyFetch) {
			$varName = TypeComparer::getChainedPropertyFetchName($expr);
			if ($varName !== null) {
				$currentScope = $scope->getCurrentScope();
				$var = $currentScope?->getVarObject($varName);
				if ($var && $var->mayBeNull) {
					return true;
				}
			}
		}
		
		// For chained assignments ($a = $b = $c), check if the right side is an assignment
		if ($expr instanceof Node\Expr\Assign) {
			return $this->getMayBeNullFromExpr($expr->expr, $scope);
		}
		
		return false;
	}

	function setValueType(Node\Expr $var, ?Node $valueType, bool $mayBeNull, ScopeStack $scope) {
		if ($var instanceof Node\Expr\Variable && gettype($var->name) == "string") {
			$overrides = Config::shouldUseDocBlockForInlineVars() ? $var->getAttribute('namespacedInlineVar') : [];
			// If it's in overrides, then it was already set by a DocBlock @var
			if (!isset($overrides[$var->name])) {
				$scope->setVarType($var->name, TypeComparer::getUniqueTypes($valueType), $var->getLine());
				
				// Propagate mayBeNull flag to the target variable
				$currentScope = $scope->getCurrentScope();
				$targetVar = $currentScope?->getVarObject($var->name);
				if ($targetVar) {
					$targetVar->mayBeNull = $mayBeNull;
				}
			}
		} elseif ($var instanceof Node\Expr\PropertyFetch) {
			$varName = TypeComparer::getChainedPropertyFetchName($var);
			if ($varName !== null) {
				$scope->setVarType($varName, TypeComparer::getUniqueTypes($valueType), $var->getLine());
				
				// Propagate mayBeNull flag to the target variable
				$currentScope = $scope->getCurrentScope();
				$targetVar = $currentScope?->getVarObject($varName);
				if ($targetVar) {
					$targetVar->mayBeNull = $mayBeNull;
				}
			}
		}
	}

	function handleAssignment(Node\Expr $var, ScopeStack $scope) {
		if ($var instanceof Node\Expr\Variable && gettype($var->name) == "string") {
			$varName = strval($var->name);
			$var->setAttribute('assignment', true);
			$scope->setVarWritten($varName, $var->getLine());
		} elseif ($var instanceof List_ || $var instanceof Node\Expr\Array_) {
			// We're not going to examine a potentially complex right side of the assignment, so just set all vars to unknown.
			foreach ($var->items as $innerVar) {
				if ($innerVar) {
					/** @var Node\Expr\ArrayItem $var */
					$value = $innerVar->value;
					if ($innerVar->key == null && $value && $value instanceof Variable && gettype($value->name) == "string") {
						//list() = or [$a,$b] = OR // return list($a, $b)
						if (!$scope->getVarExists($value->name)) {
							$value->setAttribute('assignment', true);
							$scope->setVarType(strval($value->name), null, $innerVar->getLine());
							$scope->setVarWritten(strval($value->name), $innerVar->getLine());
						} else {
							$scope->setVarUsed($value->name);
						}
					} elseif ($innerVar->key instanceof Node\Scalar\String_ && $value instanceof Variable) {
						// list("key1"=>$a, "key2"=>$b) or ["key1"=>$a, "key2"=>$b]
						$scope->setVarUsed($value->name);
					}
				}
			}
		} elseif ($var instanceof Node\Expr\ArrayDimFetch) {
			$subVar = $var;
			do {
				$subVar = $subVar->var ?? null;
			} while (!empty($subVar) && !$subVar instanceof Variable);

			if (!empty($subVar) && gettype($subVar->name) == "string") {
				$subVar->setAttribute('assignment', true);
				$scope->setVarWritten($subVar->name, $var->getLine());
			}
		}
	}

	function onEnter(Node $node, SymbolTable $table, ScopeStack $scopeStack): void {
		if (!$node instanceof List_) {
			$this->handleAssignment($node->var, $scopeStack);
		}
	}
}
