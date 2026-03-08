<?php

// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps

namespace BambooHR\Guardrail\Evaluators;

use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use PhpParser\Node;

class Switch_ implements OnEnterEvaluatorInterface, OnExitEvaluatorInterface {
	function getInstanceType(): array|string {
		return [Node\Stmt\Switch_::class, Node\Stmt\Case_::class];
	}

	function onEnter(Node $node, SymbolTable $table, ScopeStack $scopeStack): void {
		if ($node instanceof Node\Stmt\Switch_) {
			// Store parent scope for later merging
			$node->setAttribute('switch-parent-scope', $scopeStack->getCurrentScope());
			$node->setAttribute('switch-case-scopes', []);
			$node->setAttribute('switch-exited-cases', []);
			$node->setAttribute('switch-has-default', $this->hasDefaultCase($node));
			$node->setAttribute('switch-current-case-index', -1);
			$node->setAttribute('switch-previous-fell-through', false);
			
			// Store reference to this switch on all case nodes
			foreach ($node->cases as $case) {
				$case->setAttribute('parent-switch', $node);
			}
		}

		if ($node instanceof Node\Stmt\Case_) {
			$parentSwitch = $node->getAttribute('parent-switch');
			if (!$parentSwitch) {
				return;
			}
			
			$caseIndex = $parentSwitch->getAttribute('switch-current-case-index') + 1;
			$parentSwitch->setAttribute('switch-current-case-index', $caseIndex);
			
			$previousFellThrough = $parentSwitch->getAttribute('switch-previous-fell-through');
			
			if ($previousFellThrough) {
				// Continue with current scope (fall-through from previous case)
				$caseScope = $scopeStack->getCurrentScope();
			} else {
				// Create new scope from parent
				$parentScope = $parentSwitch->getAttribute('switch-parent-scope');
				$caseScope = $parentScope->getScopeClone();
				$scopeStack->pushScope($caseScope);
			}
			
			$node->setAttribute('case-scope-index', $caseIndex);
			$node->setAttribute('case-fell-through', $previousFellThrough);
		}
	}

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): void {
		if ($node instanceof Node\Stmt\Case_) {
			$parentSwitch = $node->getAttribute('parent-switch');
			if (!$parentSwitch) {
				return;
			}
			
			$caseIndex = $node->getAttribute('case-scope-index');
			$fellThrough = $node->getAttribute('case-fell-through');
			$exitsOrBreaks = $this->caseExitsOrBreaks($node);
			
			// Only store scope if we created a new one (didn't fall through)
			if (!$fellThrough) {
				$caseScope = $scopeStack->getCurrentScope();
				$caseScopes = $parentSwitch->getAttribute('switch-case-scopes');
				$caseScopes[$caseIndex] = $caseScope;
				$parentSwitch->setAttribute('switch-case-scopes', $caseScopes);
				
				// Track if this case exits early (return/throw/continue, but NOT break)
				$actuallyExits = $this->caseActuallyExits($node);
				if ($actuallyExits) {
					$exited = $parentSwitch->getAttribute('switch-exited-cases');
					$exited[] = $caseIndex;
					$parentSwitch->setAttribute('switch-exited-cases', $exited);
				}
				
				// Pop scope if it exits or breaks (doesn't fall through)
				if ($exitsOrBreaks) {
					$scopeStack->popScope();
					$parentSwitch->setAttribute('switch-previous-fell-through', false);
				} else {
					// Case falls through - leave scope for next case
					$parentSwitch->setAttribute('switch-previous-fell-through', true);
				}
			} else {
				// We fell through from previous case
				if ($exitsOrBreaks) {
					// This case exits, so we need to store the scope
					$caseScope = $scopeStack->getCurrentScope();
					$caseScopes = $parentSwitch->getAttribute('switch-case-scopes');
					$caseScopes[$caseIndex] = $caseScope;
					$parentSwitch->setAttribute('switch-case-scopes', $caseScopes);
					
					// Track if this case actually exits (not just breaks)
					$actuallyExits = $this->caseActuallyExits($node);
					if ($actuallyExits) {
						$exited = $parentSwitch->getAttribute('switch-exited-cases');
						$exited[] = $caseIndex;
						$parentSwitch->setAttribute('switch-exited-cases', $exited);
					}
					
					$scopeStack->popScope();
					$parentSwitch->setAttribute('switch-previous-fell-through', false);
				}
				// If it doesn't exit, continue falling through
			}
		}

		if ($node instanceof Node\Stmt\Switch_) {
			// Clean up any remaining scope from fall-through
			if ($node->getAttribute('switch-previous-fell-through')) {
				$scopeStack->popScope();
			}
			
			// Merge all case scopes
			$parentScope = $scopeStack->getCurrentScope();
			$caseScopes = $node->getAttribute('switch-case-scopes');
			$exitedCases = $node->getAttribute('switch-exited-cases');
			$hasDefault = $node->getAttribute('switch-has-default');
			
			// If there's no default case, add the original parent scope as an implicit branch
			// (represents the path where no case matches)
			if (!$hasDefault) {
				$originalParentScope = $node->getAttribute('switch-parent-scope');
				if ($originalParentScope) {
					$caseScopes[] = $originalParentScope;
				}
			}
			
			// Merge branches into parent scope
			$parentScope->mergeBranches($caseScopes, $exitedCases, false);
		}
	}

	/**
	 * Check if switch has a default case
	 */
	private function hasDefaultCase(Node\Stmt\Switch_ $switch): bool {
		foreach ($switch->cases as $case) {
			if ($case->cond === null) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check if a case exits or breaks
	 */
	private function caseExitsOrBreaks(Node\Stmt\Case_ $case): bool {
		if (empty($case->stmts)) {
			return false; // Empty case falls through
		}
		
		$lastStmt = end($case->stmts);
		
		return $lastStmt instanceof Node\Stmt\Break_ ||
		       $lastStmt instanceof Node\Stmt\Return_ ||
		       $lastStmt instanceof Node\Stmt\Throw_ ||
		       $lastStmt instanceof Node\Stmt\Continue_ ||
		       ($lastStmt instanceof Node\Stmt\Expression && 
		        $lastStmt->expr instanceof Node\Expr\Exit_);
	}
	
	/**
	 * Check if a case actually exits (return/throw/continue) - NOT break
	 */
	private function caseActuallyExits(Node\Stmt\Case_ $case): bool {
		if (empty($case->stmts)) {
			return false;
		}
		
		$lastStmt = end($case->stmts);
		
		return $lastStmt instanceof Node\Stmt\Return_ ||
		       $lastStmt instanceof Node\Stmt\Throw_ ||
		       $lastStmt instanceof Node\Stmt\Continue_ ||
		       ($lastStmt instanceof Node\Stmt\Expression && 
		        $lastStmt->expr instanceof Node\Expr\Exit_);
	}

	/**
	 * Find parent switch statement
	 */
	private function findParentSwitch(Node $node): ?Node\Stmt\Switch_ {
		// Walk up the parent chain to find the switch
		$current = $node;
		while ($current = $current->getAttribute('parent')) {
			if ($current instanceof Node\Stmt\Switch_) {
				return $current;
			}
		}
		return null;
	}
}
