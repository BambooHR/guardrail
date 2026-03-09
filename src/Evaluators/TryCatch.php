<?php

namespace BambooHR\Guardrail\Evaluators;

use BambooHR\Guardrail\Scope\Scope;
use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use PhpParser\Node;

class TryCatch implements OnEnterEvaluatorInterface, OnExitEvaluatorInterface {
	function getInstanceType(): array|string {
		return [Node\Stmt\TryCatch::class, Node\Stmt\Catch_::class, Node\Stmt\Finally_::class];
	}

	function onEnter(Node $node, SymbolTable $table, ScopeStack $scopeStack): void {
		if ($node instanceof Node\Stmt\TryCatch) {
			// Clone and store parent scope snapshot - all blocks will clone from this
			$parentScope = $scopeStack->getCurrentScope();
			$parentSnapshot = $parentScope->getScopeClone();
			$node->setAttribute('try-parent-scope', $parentSnapshot);
			
			// Create ALL independent scopes upfront from parent snapshot
			// Try scope
			$tryScope = $parentSnapshot->getScopeClone();
			$node->setAttribute('try-scope', $tryScope);
			
			// Catch scopes - one for each catch block
			foreach ($node->catches as $i => $catch) {
				$catchScope = $parentSnapshot->getScopeClone();
				$catch->setAttribute('catch-scope', $catchScope);
				$catch->setAttribute('catch-index', $i);
			}
			
			// Finally scope if it exists
			if ($node->finally !== null) {
				$finallyScope = $parentSnapshot->getScopeClone();
				$node->finally->setAttribute('finally-scope', $finallyScope);
			}
			
			// Now push try scope to stack for execution
			$scopeStack->pushScope($tryScope);
		}

		if ($node instanceof Node\Stmt\Catch_) {
			// Pop previous scope and push this catch's pre-created scope
			$scopeStack->popScope();
			$catchScope = $node->getAttribute('catch-scope');
			if ($catchScope) {
				$scopeStack->pushScope($catchScope);
			}
		}

		if ($node instanceof Node\Stmt\Finally_) {
			// Pop previous scope and push finally's pre-created scope
			$scopeStack->popScope();
			$finallyScope = $node->getAttribute('finally-scope');
			if ($finallyScope) {
				$scopeStack->pushScope($finallyScope);
			}
		}
	}

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): void {
		if ($node instanceof Node\Stmt\TryCatch) {
			// Collect branch scopes from node attributes
			$branches = [];
			
			// Pop last scope (last catch or finally)
			$scopeStack->popScope();
			
			// Add try scope
			$tryScope = $node->getAttribute('try-scope');
			$branches[] = $tryScope;
			
			// Add catch scopes
			foreach ($node->catches as $catch) {
				$catchScope = $catch->getAttribute('catch-scope');
				if ($catchScope) {
					$branches[] = $catchScope;
				}
			}
			
			// Handle finally block
			if ($node->finally !== null) {
				$finallyScope = $node->finally->getAttribute('finally-scope');
				
				// Finally variables are guaranteed - merge directly into parent
				$parentScope = $scopeStack->getCurrentScope();
				$parentScope->merge($finallyScope);
				
				// Now merge try/catch branches - variables defined in these get mayBeUnset
				$parentScope->mergeBranches($branches, [], false);
			} else {
				// No finally - add implicit "no exception" branch (parent snapshot)
				$parentSnapshot = $node->getAttribute('try-parent-scope');
				$implicitNoExceptionBranch = $parentSnapshot->getScopeClone();
				$branches[] = $implicitNoExceptionBranch;
				
				// Merge all branches
				$parentScope = $scopeStack->getCurrentScope();
				$parentScope->mergeBranches($branches, [], false);
			}
		}
	}

}
