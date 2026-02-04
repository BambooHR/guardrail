<?php

namespace BambooHR\Guardrail\Evaluators\Expression;

use BambooHR\Guardrail\Evaluators\ExpressionInterface;
use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeComparer;
use PhpParser\Node;

class Ternary implements ExpressionInterface {
	function getInstanceType(): string {

		return Node\Expr\Ternary::class;
	}

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): ?Node {

		/** @var Node\Expr\Ternary $expr */
		$expr = $node;
		$type1 = TypeComparer::removeNullOption(($expr->if ?: $expr->cond)->getAttribute(TypeComparer::INFERRED_TYPE_ATTR));
		$type2 = $expr->else->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);
		return TypeComparer::getUniqueTypes($type1, $type2);
	}
}
