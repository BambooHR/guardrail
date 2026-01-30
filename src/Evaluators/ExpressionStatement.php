<?php

namespace BambooHR\Guardrail\Evaluators;

use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use PhpParser\Node;

class ExpressionStatement implements OnExitEvaluatorInterface
{
	function getInstanceType(): array|string {
		return Node\Stmt\Expression::class;
	}

	public function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): void {
	//	echo "Complete expression statement\n";
	}
}
