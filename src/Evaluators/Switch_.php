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
			// Clone and store parent scope snapshot
			$parentScope = $scopeStack->getCurrentScope();
			$parentSnapshot = $parentScope->getScopeClone();
			$node->setAttribute('switch-parent-scope', $parentSnapshot);
			$node->setAttribute('switch-has-default', $this->hasDefaultCase($node));
			
			// Create scopes for each case upfront
			// Each case gets TWO scopes: one for "expression matches, enter case" and one for "expression doesn't match, go to next case"
			$currentNextCaseScope = $parentSnapshot->getScopeClone(); // Start with parent for first case
			
			foreach ($node->cases as $i => $case) {
				assert($case instanceof Node\Stmt\Case_);
				$case->setAttribute('parent-switch', $node);
				$case->setAttribute('case-index', $i);
				
				// The "next case" scope is what we evaluate the case expression against
				$case->setAttribute('case-expression-scope', $currentNextCaseScope);
				
				// Split: one branch enters this case, other branch goes to next case
				// For now, create the "enter case" scope - we'll handle assertions when we enter the case
				$enterCaseScope = $currentNextCaseScope->getScopeClone();
				$case->setAttribute('case-enter-scope', $enterCaseScope);
				
				// The "next case" scope for the following case (if expression doesn't match)
				$nextCaseScope = $currentNextCaseScope->getScopeClone();
				$case->setAttribute('case-next-scope', $nextCaseScope);
				
				// Update for next iteration
				$currentNextCaseScope = $nextCaseScope;
			}
			
			// Track which case we're currently in and scopes to merge
			$node->setAttribute('switch-current-case-index', -1);
			$node->setAttribute('switch-completed-scopes', []);
			$node->setAttribute('switch-exited-cases', []);
		}

		if ($node instanceof Node\Stmt\Case_) {
			$parentSwitch = $node->getAttribute('parent-switch');
			if (!$parentSwitch) {
				return;
			}
			
			$caseIndex = $node->getAttribute('case-index');
			$parentSwitch->setAttribute('switch-current-case-index', $caseIndex);
			
			// Check if previous case fell through
			$previousCaseIndex = $caseIndex - 1;
			$fellThrough = false;
			if ($previousCaseIndex >= 0) {
				$previousCase = $parentSwitch->cases[$previousCaseIndex];
				$fellThrough = !$this->caseExitsOrBreaks($previousCase);
			}
			
			if ($fellThrough) {
				// Merge previous case's scope into this case's enter scope
				$previousCase = $parentSwitch->cases[$previousCaseIndex];
				$previousCaseScope = $scopeStack->getCurrentScope();
				$enterCaseScope = $node->getAttribute('case-enter-scope');
				
				// Merge previous case state into this case
				if ($enterCaseScope !== null) {
					$enterCaseScope->merge($previousCaseScope);
				}
				
				// Continue using the same scope on stack (fall-through)
				$node->setAttribute('case-fell-through', true);
			} else {
				// Start fresh with this case's enter scope
				$enterCaseScope = $node->getAttribute('case-enter-scope');
				$scopeStack->pushScope($enterCaseScope);
				$node->setAttribute('case-fell-through', false);
			}
		}
	}

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): void {
		if ($node instanceof Node\Stmt\Case_) {
			$parentSwitch = $node->getAttribute('parent-switch');
			if (!$parentSwitch) {
				return;
			}
			
			$caseIndex = $node->getAttribute('case-index');
			$fellThrough = $node->getAttribute('case-fell-through');
			$exitsOrBreaks = $this->caseExitsOrBreaks($node);
			$actuallyExits = $this->caseActuallyExits($node);
			
			// Get the current scope from stack (has all the case's modifications)
			$currentCaseScope = $scopeStack->getCurrentScope();
			
			if ($exitsOrBreaks) {
				// Case exits or breaks - save this scope for merging
				$completedScopes = $parentSwitch->getAttribute('switch-completed-scopes');
				$completedScopes[$caseIndex] = $currentCaseScope;
				$parentSwitch->setAttribute('switch-completed-scopes', $completedScopes);
				
				// Track if this case actually exits early (not just break)
				if ($actuallyExits) {
					$exited = $parentSwitch->getAttribute('switch-exited-cases');
					$exited[] = $caseIndex;
					$parentSwitch->setAttribute('switch-exited-cases', $exited);
				}
				
				// Pop scope if we didn't fall through
				if (!$fellThrough) {
					$scopeStack->popScope();
				}
			}
			// If doesn't exit/break, leave scope on stack for fall-through to next case
		}

		if ($node instanceof Node\Stmt\Switch_) {
			// Pop any remaining scope from last case if it fell through
			$lastCase = end($node->cases);
			if ($lastCase && !$this->caseExitsOrBreaks($lastCase)) {
				$scopeStack->popScope();
			}
			
			// Collect all completed case scopes from node attributes
			$branches = [];
			$completedScopes = $node->getAttribute('switch-completed-scopes');
			
			foreach ($completedScopes as $caseIndex => $caseScope) {
				$branches[] = $caseScope;
			}
			
			$exitedCases = $node->getAttribute('switch-exited-cases');
			$hasDefault = $node->getAttribute('switch-has-default');
			
			// If there's no default case, add implicit "no match" branch
			if (!$hasDefault) {
				// The last case's "next-scope" represents the "no case matched" path
				$lastCase = end($node->cases);
				if ($lastCase) {
					$noMatchScope = $lastCase->getAttribute('case-next-scope');
					$branches[] = $noMatchScope;
				}
			}
			
			// Merge all branches into parent scope
			$parentScope = $scopeStack->getCurrentScope();
			$parentScope->mergeBranches($branches, $exitedCases, false);
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
