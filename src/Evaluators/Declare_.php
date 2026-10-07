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

		$value = $this->strictTypesValue($node);
		if ($value === null) {
			return;
		}
		if ($node->stmts != null) {
			$scopeStack->pushScope($scopeStack->getScopeClone());
		}
		$scopeStack->getCurrentScope()->isStrict = $value;
	}

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): void {

		if ($this->strictTypesValue($node) === null) {
			return;
		}
		if ($node->stmts != null) {
			$scopeStack->popScope();
		}
	}

	/**
	 * @return bool|null true when strict_types is 1, false when it is present but not 1, null when absent
	 */
	private function strictTypesValue(Node $node): ?bool {
		foreach ($node->declares as $declare) {
			if (strval($declare->key) == "strict_types") {
				return $declare->value instanceof Node\Scalar\LNumber && $declare->value->value === 1;
			}
		}
		return null;
	}
}
