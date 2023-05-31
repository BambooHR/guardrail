<?php

namespace BambooHR\Guardrail\Evaluators;

use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;

class Global_ implements OnExitEvaluatorInterface
{

	function getInstanceType(): array|string
	{
		return Node\Stmt\Global_::class;
	}

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): void
	{
		/** @var Node\Stmt\Global_ $global */
		$global = $node;
		foreach ($global->vars as $var) {
			if ($var instanceof Variable) {
				if (gettype($var->name) == "string") {
					$scopeStack->getCurrentScope()->setVarType(strval($var->name), null, $var->getLine());
				}
			}
		}
	}
}