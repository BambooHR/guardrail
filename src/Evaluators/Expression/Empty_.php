<?php

namespace BambooHR\Guardrail\Evaluators\Expression;

use BambooHR\Guardrail\NodePatterns;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeComparer;
use PhpParser\Node;

class Empty_ implements \BambooHR\Guardrail\Evaluators\ExpressionInterface
{

	function getInstanceType(): array|string
	{
		return [Node\Expr\Empty_::class, Node\Expr\BooleanNot::class, Node\Expr\Isset_::class];
	}

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): ?Node
	{
		if ($node instanceof Node\Expr\Isset_) {
			if (count($node->vars)==1) {
				// isset() removes "null" from the true assertions.
				$varName = $this->getVarName($node->vars[0]);
				if ($varName) {
					$scope = $scopeStack->getCurrentScope()->getScopeClone();
					if ($scope->getVarType($varName)) {
						$parentType = $scope->getVarType($varName);
					} else {
						$parentType = $node->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);
					}
					if ($parentType) {
						$parentType = TypeComparer::removeNullOption($parentType);
						$node->setAttribute(TypeComparer::INFERRED_TYPE_ATTR, $parentType);
					}
					TypeComparer::removeNullOptions($node->vars[0], $scope, $node->vars[0]->getLine());
					$node->setAttribute('assertsTrue', $scope);

					$falseScope = $scopeStack->getCurrentScope()->getScopeClone();
					$falseScope->setVarType($varName, TypeComparer::identifierFromName("null"), $node->getLine());
					$node->setAttribute('assertsFalse', $falseScope);
				}
			}
		} else if ($node instanceof Node\Expr\Empty_) {
			// Empty doesn't mean much when true, but when !empty() it means that null is not an option.
			$varName = $this->getVarName($node->expr);
			if ($varName) {
				$scope = $scopeStack->getCurrentScope()->getScopeClone();

				$scope->setVarType($varName, TypeComparer::removeNullOption($scope->getVarType($varName)), $node->getLine());
				$node->setAttribute('assertsFalse', $scope);
			}
		} else if ($node instanceof Node\Expr\BooleanNot) {
			/** @var Node\Expr\BooleanNot $not */
			$not = $node;
			if ($not->expr->hasAttribute('assertsTrue')) {
				$not->setAttribute('assertsFalse', $not->expr->getAttribute('assertsTrue'));
			}
			if ($not->expr->hasAttribute('assertsFalse')) {
				$not->setAttribute('assertsTrue', $not->expr->getAttribute('assertsFalse'));
			}
			if (
				!$not->hasAttribute('assertsTrue') &&
				!$not->hasAttribute('assertsFalse') &&
				$not->expr instanceof Node\Expr\Variable || $not->expr instanceof Node\Expr\PropertyFetch
			) {
				$scope = $scopeStack->getCurrentScope()->getScopeClone();
				TypeComparer::removeNullOptions($not->expr, $scope, $not->getLine());
				$not->setAttribute('assertsFalse', $scope);
			}
		}
		return TypeComparer::identifierFromName("bool");
	}

	private function getVarName(Node\Expr $var): ?string {
		$varName = NodePatterns::getVariableOrPropertyName($var);
		return $varName;
	}
}