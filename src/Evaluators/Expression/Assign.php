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
		$valueType = $assign->expr->getAttribute(TypeComparer::INFERRED_TYPE_ATTR );
		$this->setValueType($assign->var, $valueType, $scopeStack);
	//	echo "Assigning value type: ".($assign->var->name)."$valueType\n";
	//	$scopeStack->dump();
		return $valueType;
	}

	function setValueType(Node\Expr $var, ?Node $valueType, ScopeStack $scope) {
		if ($var instanceof Node\Expr\Variable && gettype($var->name) == "string") {
			$overrides = Config::shouldUseDocBlockForInlineVars() ? $var->getAttribute('namespacedInlineVar') : [];
			// If it's in overrides, then it was already set by a DocBlock @var
			if (!isset($overrides[$var->name])) {
				$scope->setVarType($var->name, TypeComparer::getUniqueTypes($valueType), $var->getLine());
			}
		} elseif ($var instanceof Node\Expr\PropertyFetch) {
			$varName = TypeComparer::getChainedPropertyFetchName($var);
			if ($varName !== null) {
				$scope->setVarType($varName, TypeComparer::getUniqueTypes($valueType), $var->getLine());
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