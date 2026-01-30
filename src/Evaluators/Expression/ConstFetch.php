<?php

namespace BambooHR\Guardrail\Evaluators\Expression;

use BambooHR\Guardrail\Evaluators\ExpressionInterface;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeComparer;
use PhpParser\Node;

class ConstFetch implements ExpressionInterface
{
	function getInstanceType(): string {
		return Node\Expr\ConstFetch::class;
	}

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): ?Node {
		/** @var Node\Expr\ConstFetch $constFetch */
		$constFetch = $node;
		return $this->getType($table, $constFetch);
	}

	function getType(SymbolTable $table, Node\Expr\ConstFetch $expr): ?Node\Identifier {
		if (strcasecmp($expr->name, "null") == 0) {
			return TypeComparer::identifierFromName("null");
		}
		if (strcasecmp($expr->name, "false") == 0 || strcasecmp($expr->name, "true") == 0) {
			return TypeComparer::identifierFromName($expr->name);
		}
		if (defined($expr->name)) {
			// Guardrail doesn't declare any global constants.  Any that exist are from the runtime.
			return TypeComparer::identifierFromName("mixed");
		}
		if ($table->isDefined($expr->name)) {
			return TypeComparer::identifierFromName("mixed");
		}
		return TypeComparer::identifierFromName("mixed");
	}
}
