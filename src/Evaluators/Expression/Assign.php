<?php

namespace BambooHR\Guardrail\Evaluators\Expression;

use BambooHR\Guardrail\Config;
use BambooHR\Guardrail\Evaluators\ExpressionInterface;
use BambooHR\Guardrail\Evaluators\OnEnterEvaluatorInterface;
use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeComparer;
use PhpParser\Node;
use PhpParser\Node\Expr\List_;
use PhpParser\Node\Expr\Variable;

class Assign implements ExpressionInterface, OnEnterEvaluatorInterface
{

	function getInstanceType(): array|string
	{
		return [Node\Expr\Assign::class, Node\Expr\AssignRef::class, Node\Expr\List_::class, Node\Expr\Array_::class];
	}

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): ?Node
	{
		if ($node instanceof List_ || $node instanceof Node\Expr\Array_) {
			return TypeComparer::identifierFromName("array");
		}

		/** @var Node\Expr\Assign $assign */
		$assign = $node;
		$valueType=$assign->expr->getAttribute(TypeComparer::INFERRED_TYPE_ATTR );
		$this->handleAssignment($assign->var, $valueType, $scopeStack);
		return $valueType;
	}

	function handleAssignment(Node\Expr $var, ?Node $valueType, ScopeStack $scope) {
		if ($var instanceof Node\Expr\Variable && gettype($var->name) == "string") {
			$overrides = Config::shouldUseDocBlockForInlineVars() ? $var->getAttribute('namespacedInlineVar') : [];
			$varName = strval($var->name);

			// If it's in overrides, then it was already set by a DocBlock @var
			if (!isset($overrides[$varName])) {
				$scope->setVarType($varName, $valueType, $var->getLine());
				$scope->setVarWritten($varName, $var->getLine());
			}
		} else if ($var instanceof List_ || $var instanceof Node\Expr\Array_) {
			// We're not going to examine a potentially complex right side of the assignment, so just set all vars to unknown.
			foreach ($var->items as $innerVar) {
				if ($innerVar) {
					/** @var Node\Expr\ArrayItem $var */
					$value = $innerVar->value;
					if ($innerVar->key == null && $value && $value instanceof Variable && gettype($value->name) == "string") {
						// list() = or [$a,$b] =
						$value->setAttribute('assignment',true);
						$scope->setVarType(strval($value->name), null, $innerVar->getLine());
						$scope->setVarWritten(strval($value->name), $var->getLine());
					} else if($innerVar->key instanceof Node\Scalar\String_ && $value instanceof Variable) {
						// list("key1"=>$a, "key2"=>$b) or ["key1"=>$a, "key2"=>$b]
						$value->setAttribute('assignment',true);
						$scope->setVarType($innerVar->key->value, null, $innerVar->getLine());
						$scope->setVarWritten($innerVar->key->value, $var->getLine());
					}
				}
			}
		} else if ($var instanceof Node\Expr\PropertyFetch) {
			$varName = TypeComparer::getChainedPropertyFetchName($var);
			if ($varName !== null) {
				$scope->setVarType($varName, $valueType, $var->getLine());
			}
		} else if ($var instanceof Node\Expr\Assign || $var instanceof Node\Expr\AssignRef) {
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
		if ($node instanceof Node\Expr) {
			$this->handleAssignment($node, null, $scopeStack);
		}
	}
}