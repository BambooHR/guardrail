<?php

namespace BambooHR\Guardrail\Evaluators\Expression;

use BambooHR\Guardrail\Evaluators\ExpressionInterface;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeComparer;
use PhpParser\Node;

class ShellExec implements ExpressionInterface
{
	function getInstanceType(): array|string {
		return [Node\Expr\ShellExec::class, Node\Expr\Eval_::class];
	}

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): ?Node {
		if ($node instanceof Node\Expr\ShellExec) {

			return TypeComparer::identifierFromName("string");
		} else {
			return null;
		}
	}
}
