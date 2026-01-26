<?php
// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps

namespace BambooHR\Guardrail\Evaluators\Expression;

use BambooHR\Guardrail\Evaluators\ExpressionInterface;
use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeComparer;
use PhpParser\Node;

class Clone_ implements ExpressionInterface {
	function getInstanceType(): string {
		return Node\Expr\Clone_::class;
	}

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): ?Node {
		/** @var Node\Expr\Clone_ $clone */
		$clone = $node;
		return $clone->expr->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);
	}
}