<?php

namespace BambooHR\Guardrail\Evaluators\Expression;

use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use PhpParser\Node;

class NoOp implements \BambooHR\Guardrail\Evaluators\ExpressionInterface
{
	function getInstanceType(): array|string {
		return [Node\Expr\Include_::class, Node\Expr\ErrorSuppress::class, Node\Expr\Throw_::class];
	}

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): ?Node {
		// No op, unknown return type
		return null;
	}
}
