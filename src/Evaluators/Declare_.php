<?php
// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps

namespace BambooHR\Guardrail\Evaluators;

use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use PhpParser\Node;

class Declare_ implements OnExitEvaluatorInterface, OnEnterEvaluatorInterface
{

	function getInstanceType(): string {
		return Node\Stmt\Declare_::class;
	}

	function onEnter(Node $node, SymbolTable $table, ScopeStack $scopeStack): void {
		if (count($node->declares) > 0 && strval($node->declares[0]->key) == "strict") {
			$value = ($node->declares[0]->value instanceof Node\Scalar\LNumber && $node->declares[0]->value->value === 1) ? true : false;
			if ($node->stmts != null) {
				$this->scopeStack->pushScopeClone();
			}
			$this->scopeStack->getCurrentScope()->isStrict = $value;
		}
	}

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): void {
		if (count($node->declares) > 0 && strval($node->declares[0]->key) == "strict") {
			if ($node->stmts != null) {
				$scopeStack->popScope();
			}
		}
	}
}