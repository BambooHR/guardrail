<?php

// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps

namespace BambooHR\Guardrail\Evaluators\Expression;

use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeComparer;
use PhpParser\Node;

class Print_ implements \BambooHR\Guardrail\Evaluators\ExpressionInterface
{

	function getInstanceType(): array|string {
		return Node\Expr\Print_::class;
	}

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): ?Node {
		return TypeComparer::identifierFromName("int");
	}
}