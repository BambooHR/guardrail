<?php

// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps

namespace BambooHR\Guardrail\Evaluators;

use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use PhpParser\Node;
use PhpParser\Node\Stmt\ElseIf_;

class If_ implements OnEnterEvaluatorInterface, OnExitEvaluatorInterface {
	function getInstanceType(): array|string {
		return [Node\Stmt\If_::class, Node\Stmt\Else_::class,Node\Stmt\ElseIf_::class];
	}

	function onEnter(Node $node, SymbolTable $table, ScopeStack $scopeStack): void {
		if ($node instanceof Node\Stmt\If_) {
			$thenBranch = $scopeStack->getCurrentScope()->getScopeClone();
			$scopeStack->pushScope($thenBranch);
			$cond = self::getIfCond($node);
			$cond->setAttribute('grab-if-cond-scope-on-leave', true);
		} elseif ( $node instanceof Node\Stmt\ElseIf_) {
			$scopeStack->swapTopTwoScopes();

			$thenBranch = $scopeStack->getCurrentScope()->getScopeClone();
			$scopeStack->pushScope($thenBranch);

			$cond = self::getIfCond($node);
			$cond->setAttribute('grab-if-cond-scope-on-leave', true);
		} elseif ( $node instanceof Node\Stmt\Else_) {
			$scopeStack->swapTopTwoScopes();
		}
	}

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): void {
		if ($node instanceof Node\Stmt\If_) {
			if ($node->else == null &&
				count($node->elseifs) == 0 &&
				( end($node->stmts) instanceof Node\Stmt\Return_ || end($node->stmts) instanceof Node\Stmt\Throw_ || end($node->stmts) instanceof Node\Stmt\Continue_)
			) {
				// Our condition was true and then never returned, that means that
				// the else version of that scope if true for the remainder of this function.
				$scopeStack->popScope();
				if ($node->cond->hasAttribute('assertsFalse')) {
					$else = $node->cond->getAttribute('assertsFalse');
					$scopeStack->getCurrentScope()->merge($else);
				}
			} else {
				$cond = $scopeStack->popScope();
				$scopeStack->getCurrentScope()->merge($cond);

				for ($count = 0; $count < count($node->elseifs); ++$count) {
					$scopeStack->getCurrentScope()->merge($scopeStack->popScope());
				}
			}
		}
	}

	static function pushIfScope($node, ScopeStack $scopeStack) {
		$cond = self::getIfCond($node);

		// If there is an asserts true, then use it over the state on the stack.
		if ($cond->hasAttribute('assertsTrue')) {
			$scopeStack->popScope();
			$condScope = $cond->getAttribute('assertsTrue');
			$scopeStack->pushScope($condScope);
		}
		$scopeStack->swapTopTwoScopes();

		if ($cond->hasAttribute('assertsFalse')) {
			$notCondScope = $cond->getAttribute('assertsFalse');
			$scopeStack->popScope();
		} else {
			$notCondScope = $scopeStack->popScope();
		}
		$scopeStack->pushScope($notCondScope);
		$scopeStack->swapTopTwoScopes();
	}

	static public function getIfCond(Node $node) {
		if ($node instanceof Node\Expr\Ternary) {
			return $node->cond;
		}
		if ($node instanceof \PhpParser\Node\Stmt\If_) {
			return $node->cond;
		}
		if ($node instanceof ElseIf_) {
			return $node->cond;
		}
		return null;
	}
}
