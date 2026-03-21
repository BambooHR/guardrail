<?php

namespace BambooHR\Guardrail\Evaluators;

use BambooHR\Guardrail\Scope\Scope;
use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\Util;
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
				assert($catch instanceof Node\Stmt\Catch_);
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
			// Pop last scope (last catch or finally)
			$scopeStack->popScope();
			
			// Check if all catch blocks exit or throw
			$allCatchesExit = true;
			foreach ($node->catches as $catch) {
				assert($catch instanceof Node\Stmt\Catch_);
				if (!Util::allBranchesExit($catch->stmts)) {
					$allCatchesExit = false;
					break;
				}
			}
			
			$parentScope = $scopeStack->getCurrentScope();
			$tryScope = $node->getAttribute('try-scope');
			
			// If all catches exit/throw, the try block always completes successfully
			// So merge try scope directly into parent (no mayBeUnset)
			if ($allCatchesExit && count($node->catches) > 0) {
				// Handle finally block if present
				if ($node->finally !== null) {
					$finallyScope = $node->finally->getAttribute('finally-scope');
					// Finally variables are guaranteed - merge directly into parent
					$parentScope->merge($finallyScope);
				}
				
				// Merge try scope directly - variables are guaranteed to be defined
				$parentScope->merge($tryScope);
			} else {
				// Standard behavior: collect all branches
				$branches = [];
				
				// Add try scope
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
					$parentScope->merge($finallyScope);
				}
				
				// Merge try/catch branches
				// Either the try completes OR a catch executes - no implicit branch needed
				$parentScope->mergeBranches($branches, [], false);
			}
		}
	}

}
