<?php

namespace BambooHR\Guardrail\Evaluators;

use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;

class Global_ implements OnEnterEvaluatorInterface
{

	function getInstanceType(): array|string
	{
		return Node\Stmt\Global_::class;
	}

	function onEnter(Node $node, SymbolTable $table, ScopeStack $scopeStack): void
	{
		/** @var Node\Stmt\Global_ $global */
		$global = $node;
		foreach ($global->vars as $var) {
			if ($var instanceof Variable) {
				if (gettype($var->name) == "string") {
					$var->setAttribute('assignment', true);
					$scopeStack->setVarWritten(strval($var->name), $var->getLine());
					$scopeStack->setVarType(strval($var->name), null, $var->getLine());
				}
			}
		}
	}
}