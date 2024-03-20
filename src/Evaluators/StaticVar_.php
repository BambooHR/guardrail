<?php

namespace BambooHR\Guardrail\Evaluators;

use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use PhpParser\Node;

class StaticVar_ implements OnEnterEvaluatorInterface
{

	function getInstanceType(): array|string
	{
		return Node\Stmt\StaticVar::class;
	}

	function onEnter(Node $node, SymbolTable $table, ScopeStack $scopeStack): void
	{
		if ($node instanceof Node\Stmt\StaticVar) {
			// Static variables are evaluated before their assignment. We should ignore undefined checks on these variables.
			$node->var->setAttribute('assignment', true);
			$scopeStack->setVarWritten(strval($node->var->name), $node->var->getLine());
			$scopeStack->setVarType(strval($node->var->name), null, $node->var->getLine());
		}
	}
}
