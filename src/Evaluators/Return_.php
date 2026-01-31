<?php

// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps

namespace BambooHR\Guardrail\Evaluators;

use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use PhpParser\Node;

class Return_ implements OnExitEvaluatorInterface
{
	function getInstanceType(): array|string {
		return Node\Stmt\Return_::class;
	}

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): void {
	}
}
