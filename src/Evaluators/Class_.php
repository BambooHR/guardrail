<?php

namespace BambooHR\Guardrail\Evaluators;

use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use PhpParser\Node;

class Class_ implements OnEnterEvaluatorInterface, OnExitEvaluatorInterface {
	function getInstanceType(): string
	{
		return Node\Stmt\ClassLike::class;
	}

	function onEnter(Node $node, SymbolTable $table, ScopeStack $scopeStack): void {
		/** @var Node\Stmt\Class_ $class */
		$class = $node;
		$scopeStack->pushClass($class);
	}

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): void {
		$scopeStack->popClass();
	}
}