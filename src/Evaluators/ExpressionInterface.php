<?php

namespace BambooHR\Guardrail\Evaluators;

use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use PhpParser\Node;

interface ExpressionInterface
{
	function getInstanceType(): array|string;
	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): ?Node;
}