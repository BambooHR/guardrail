<?php

namespace BambooHR\Guardrail\Evaluators;

use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\Scope\ScopeStack;
use PhpParser\Node;

interface OnExitEvaluatorInterface extends EvaluatorInterface {

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): void;
}