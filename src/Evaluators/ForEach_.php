<?php

namespace BambooHR\Guardrail\Evaluators;

use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use PhpParser\Node;
use PhpParser\Node\Expr\List_;
use PhpParser\Node\Expr\Variable;

class ForEach_ implements OnEnterEvaluatorInterface
{

	function getInstanceType(): array|string
	{
		return Node\Stmt\Foreach_::class;
	}

	function onEnter(Node $node, SymbolTable $table, ScopeStack $scopeStack): void
	{
		$valueVar = $node->valueVar;
		$keyVar = $node->keyVar;
		if ($keyVar instanceof Variable) {
			if (gettype($keyVar->name) == "string") {
				$scopeStack->getCurrentScope()->setVarType(strval($keyVar->name), null, $keyVar->getLine());
			}
		}
		if ($valueVar instanceof Variable) {
			if (gettype($valueVar->name) == "string") {
				$scopeStack->getCurrentScope()->setVarType(strval($valueVar->name), null, $valueVar->getLine());
			}
		} else {
			if ($valueVar instanceof List_) {
				// Deal with traditional list($a,b,$c) style list.
				foreach ($valueVar->items as $var) {
					if ($var->key == NULL && $var->value instanceof Variable) {
						if (gettype($var->value->name) == "string") {
							$scopeStack->getCurrentScope()->setVarType(strval($var->value->name), null, $var->getLine());
						}
					}
				}
			}
		}
	}
}