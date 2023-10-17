<?php

namespace BambooHR\Guardrail\Evaluators\Expression;

use BambooHR\Guardrail\Evaluators\ExpressionInterface;
use BambooHR\Guardrail\NodePatterns;
use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeComparer;
use PhpParser\Node;

class Variable implements ExpressionInterface {
	function getInstanceType(): string {
		return Node\Expr\Variable::class;
	}

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): ?Node {
		$expr = $node;
		$returnType = null;
		if (is_string($expr->name)) {
			$class = $scopeStack->getCurrentClass();
			$varName = $expr->name;
			if ($varName == "this" && $class) {
				$name = strval($class->namespacedName ?: $class->name );
				$returnType = ($name ? TypeComparer::nameFromName($name) : null );
			} else if ($varName == "_GET" || $varName == "_POST" || $varName == "_COOKIE" || $varName == "_REQUEST") {
				$returnType = TypeComparer::identifierFromName("array");
			} else {
				$parent = $scopeStack->getParent();
				if (NodePatterns::parentNodeExpectsBool($parent, $expr)) {
					$var = NodePatterns::getVariableOrPropertyName($expr);
					if ($var) {
						$varScope = $scopeStack->getCurrentScope();
						$varScope->setVarType($var, TypeComparer::removeNullOption( $varScope->getVarType($var)), $node->getLine());
						$node->setAttribute('assertsTrue', $varScope);
					}
				}
				$returnType = $scopeStack->getCurrentScope()->getVarType($varName);
			}
		}
		return $returnType;
	}
}