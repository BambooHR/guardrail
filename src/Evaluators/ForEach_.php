<?php

// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps

namespace BambooHR\Guardrail\Evaluators;

use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use PhpParser\Node;
use PhpParser\Node\Expr\List_;
use PhpParser\Node\Expr\Variable;

class ForEach_ implements OnEnterEvaluatorInterface
{
	function getInstanceType(): array|string {
		return Node\Stmt\Foreach_::class;
	}

	function onEnter(Node $node, SymbolTable $table, ScopeStack $scopeStack): void {
		$valueVar = $node->valueVar;
		$keyVar = $node->keyVar;
		if ($keyVar instanceof Variable) {
			if (gettype($keyVar->name) == "string") {
				$keyVar->setAttribute('assignment', true);
				$scopeStack->setVarWritten($keyVar->name, $keyVar->getLine());
				$scopeStack->setVarType($keyVar->name, null, $keyVar->getLine());
				$scopeStack->setVarUsed($keyVar->name);
			}
		}
		if ($valueVar instanceof Variable) {
			if (gettype($valueVar->name) == "string") {
				$valueVar->setAttribute('assignment', true);
				$scopeStack->setVarWritten($valueVar->name, $valueVar->getLine());
				$scopeStack->setVarType($valueVar->name, null, $valueVar->getLine());
				$scopeStack->setVarUsed($valueVar->name);
			}
		} else {
			if ($valueVar instanceof List_) {
				// Deal with traditional list($a,b,$c) style list.
				foreach ($valueVar->items as $var) {
					if ($var->key == null && $var->value instanceof Variable) {
						if (gettype($var->value->name) == "string") {
							$var->value->setAttribute('assignment', true);
							$scopeStack->setVarWritten(strval($var->value->name), $var->getLine());
							$scopeStack->setVarType(strval($var->value->name), null, $var->getLine());
						}
					}
				}
			}
		}
	}
}
