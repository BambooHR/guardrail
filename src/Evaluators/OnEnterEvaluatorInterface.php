<?php

namespace BambooHR\Guardrail\Evaluators;

use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use PhpParser\Node;

interface OnEnterEvaluatorInterface extends EvaluatorInterface
{
	function onEnter(Node $node, SymbolTable $table, ScopeStack $scopeStack): void;
}
