<?php

// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps

namespace BambooHR\Guardrail\Evaluators;

use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeInference\TypeAssertion;
use PhpParser\Node;
use PhpParser\Node\Stmt\ElseIf_;

class If_ implements OnEnterEvaluatorInterface, OnExitEvaluatorInterface {
	function getInstanceType(): array|string {

		return [Node\Stmt\If_::class, Node\Stmt\Else_::class,Node\Stmt\ElseIf_::class];
	}

	function onEnter(Node $node, SymbolTable $table, ScopeStack $scopeStack): void {

		if ($node instanceof Node\Stmt\If_) {
			// Store parent scope and initialize branch tracking
			$node->setAttribute('if-parent-scope', $scopeStack->getCurrentScope());
			$node->setAttribute('if-branches', []);
			$node->setAttribute('if-exited-branches', []);
			
			// Store the if condition on the else node for inverse narrowing
			if ($node->else !== null) {
				$node->else->setAttribute('if-node', $node);
			}
			
			// Create scope for then-branch and apply type narrowing
			$thenBranch = $scopeStack->getCurrentScope()->getScopeClone();
			TypeAssertion::narrowTypes($node->cond, $thenBranch, true);
			$scopeStack->pushScope($thenBranch);
			$cond = self::getIfCond($node);
			$cond->setAttribute('grab-if-cond-scope-on-leave', true);
		} elseif ($node instanceof Node\Stmt\ElseIf_) {
			$scopeStack->swapTopTwoScopes();
			
			// Create scope for elseif-branch and apply type narrowing
			$elseIfBranch = $scopeStack->getCurrentScope()->getScopeClone();
			TypeAssertion::narrowTypes($node->cond, $elseIfBranch, true);
			$scopeStack->pushScope($elseIfBranch);
			$cond = self::getIfCond($node);
			$cond->setAttribute('grab-if-cond-scope-on-leave', true);
		} elseif ($node instanceof Node\Stmt\Else_) {
			// The then-branch (or last elseif) is on top of the stack
			// We need to swap it with the parent scope so we can create the else branch from parent
			$scopeStack->swapTopTwoScopes();
			
			// Now parent is on top - create a new cloned scope for the else branch
			$elseBranch = $scopeStack->getCurrentScope()->getScopeClone();
			
			// Apply inverse narrowing from the original if condition
			// The else block runs when the if condition (and all elseif conditions) were false
			$ifNode = $node->getAttribute('if-node');
			if ($ifNode instanceof Node\Stmt\If_) {
				TypeAssertion::narrowTypes($ifNode->cond, $elseBranch, false);
				
				// Also apply inverse narrowing from all elseif conditions
				foreach ($ifNode->elseifs as $elseIf) {
					TypeAssertion::narrowTypes($elseIf->cond, $elseBranch, false);
				}
			}
			
			// Push the else branch, then swap back so parent is on bottom
			$scopeStack->pushScope($elseBranch);
			$scopeStack->swapTopTwoScopes();
		}
	}

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): void {

		if ($node instanceof Node\Stmt\If_) {
			// Check if then-branch exits early
			$thenExitsEarly = !empty($node->stmts) && 
				(end($node->stmts) instanceof Node\Stmt\Return_ || 
				 end($node->stmts) instanceof Node\Stmt\Throw_ || 
				 end($node->stmts) instanceof Node\Stmt\Continue_ ||
				 end($node->stmts) instanceof Node\Stmt\Break_);
			
			if ($thenExitsEarly && $node->else == null && count($node->elseifs) == 0) {
				// Then-branch exits and no else - apply inverse narrowing to parent scope
				$scopeStack->popScope();
				$parentScope = $scopeStack->getCurrentScope();
				TypeAssertion::narrowTypes($node->cond, $parentScope, false);
				
				if ($node->cond->hasAttribute('assertsFalse')) {
					$else = $node->cond->getAttribute('assertsFalse');
					$parentScope->merge($else);
				}
			} else {
				// Collect all branch scopes
				$branches = [];
				$exitedBranches = [];
				
				// Add then-branch
				$thenScope = $scopeStack->popScope();
				$branches[] = $thenScope;
				if ($thenExitsEarly) {
					$exitedBranches[] = 0;
				}
				
				// Add elseif branches
				for ($i = 0; $i < count($node->elseifs); ++$i) {
					$elseIfScope = $scopeStack->popScope();
					$branches[] = $elseIfScope;
					
					$elseIfStmts = $node->elseifs[$i]->stmts;
					if (!empty($elseIfStmts) && 
						(end($elseIfStmts) instanceof Node\Stmt\Return_ || 
						 end($elseIfStmts) instanceof Node\Stmt\Throw_ ||
						 end($elseIfStmts) instanceof Node\Stmt\Continue_ ||
						 end($elseIfStmts) instanceof Node\Stmt\Break_)) {
						$exitedBranches[] = count($branches) - 1;
					}
				}
				
				// Add else branch if it exists
				if ($node->else !== null) {
					$elseScope = $scopeStack->popScope();
					$branches[] = $elseScope;
					
					$elseStmts = $node->else->stmts;
					if (!empty($elseStmts) && 
						(end($elseStmts) instanceof Node\Stmt\Return_ || 
						 end($elseStmts) instanceof Node\Stmt\Throw_ ||
						 end($elseStmts) instanceof Node\Stmt\Continue_ ||
						 end($elseStmts) instanceof Node\Stmt\Break_)) {
						$exitedBranches[] = count($branches) - 1;
					}
				}
				
				// Merge all branches into parent scope
				$parentScope = $scopeStack->getCurrentScope();
				$hasImplicitBranch = ($node->else === null);
				
				// If there's an implicit branch, we need to include the original parent scope
				// (before the if statement) as one of the branches
				if ($hasImplicitBranch) {
					$originalParentScope = $node->getAttribute('if-parent-scope');
					if ($originalParentScope) {
						$branches[] = $originalParentScope;
					}
				}
				
				$parentScope->mergeBranches($branches, $exitedBranches, false);
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

	public static function getIfCond(Node $node) {

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
