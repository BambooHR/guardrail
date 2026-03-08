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
			// Clone and store parent scope - all branches will clone from this snapshot
			// We clone it now to prevent the parent scope from being modified during branch execution
			$parentScope = $scopeStack->getCurrentScope();
			$parentSnapshot = $parentScope->getScopeClone();
			$node->setAttribute('if-parent-scope', $parentSnapshot);
			
			// Set reference to this if node on all elseif and else nodes
			foreach ($node->elseifs as $elseIf) {
				$elseIf->setAttribute('parent-if', $node);
			}
			if ($node->else !== null) {
				$node->else->setAttribute('parent-if', $node);
			}
			
			// Create independent scope for then-branch
			$thenBranch = $parentScope->getScopeClone();
			TypeAssertion::narrowTypes($node->cond, $thenBranch, true);
			
			// Store then-branch scope on the node and push to stack for body execution
			$node->setAttribute('if-then-scope', $thenBranch);
			$scopeStack->pushScope($thenBranch);
			
		} elseif ($node instanceof Node\Stmt\ElseIf_) {
			// ElseIf nodes are children of the If_ node, so we need to find it
			// The parent scope is stored on the If_ node
			// For now, we'll store a reference to the if node on each elseif
			// This will be set when we process the If_ node
			$ifNode = $node->getAttribute('parent-if');
			if ($ifNode) {
				$parentScope = $ifNode->getAttribute('if-parent-scope');
				
				// Create independent scope for elseif-branch from parent
				$elseIfBranch = $parentScope->getScopeClone();
				TypeAssertion::narrowTypes($node->cond, $elseIfBranch, true);
				
				// Store on the elseif node and push to stack
				$node->setAttribute('elseif-scope', $elseIfBranch);
				$scopeStack->pushScope($elseIfBranch);
			}
			
		} elseif ($node instanceof Node\Stmt\Else_) {
			// Else node is a child of the If_ node
			$ifNode = $node->getAttribute('parent-if');
			if ($ifNode) {
				$parentScope = $ifNode->getAttribute('if-parent-scope');
				
				// Create independent scope for else-branch from parent
				$elseBranch = $parentScope->getScopeClone();
				
				// Apply inverse narrowing from if and all elseif conditions
				TypeAssertion::narrowTypes($ifNode->cond, $elseBranch, false);
				foreach ($ifNode->elseifs as $elseIf) {
					TypeAssertion::narrowTypes($elseIf->cond, $elseBranch, false);
				}
				
				// Store on the else node and push to stack
				$node->setAttribute('else-scope', $elseBranch);
				$scopeStack->pushScope($elseBranch);
			}
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
				// Collect all branch scopes from node attributes (independent clones)
				$branches = [];
				$exitedBranches = [];
				
				// Pop then-branch from stack (we're done executing it)
				$scopeStack->popScope();
				
				// Add then-branch scope from node attribute
				$thenScope = $node->getAttribute('if-then-scope');
				$branches[] = $thenScope;
				if ($thenExitsEarly) {
					$exitedBranches[] = 0;
				}
				
				// Add elseif branches from node attributes
				for ($i = 0; $i < count($node->elseifs); ++$i) {
					$elseIfNode = $node->elseifs[$i];
					$scopeStack->popScope(); // Pop from stack
					
					$elseIfScope = $elseIfNode->getAttribute('elseif-scope');
					$branches[] = $elseIfScope;
					
					$elseIfStmts = $elseIfNode->stmts;
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
					$scopeStack->popScope(); // Pop from stack
					
					$elseScope = $node->else->getAttribute('else-scope');
					$branches[] = $elseScope;
					
					$elseStmts = $node->else->stmts;
					if (!empty($elseStmts) && 
						(end($elseStmts) instanceof Node\Stmt\Return_ || 
						 end($elseStmts) instanceof Node\Stmt\Throw_ ||
						 end($elseStmts) instanceof Node\Stmt\Continue_ ||
						 end($elseStmts) instanceof Node\Stmt\Break_)) {
						$exitedBranches[] = count($branches) - 1;
					}
				} else {
					// No else - create implicit else branch from parent scope
					$parentScope = $node->getAttribute('if-parent-scope');
					$implicitElseBranch = $parentScope->getScopeClone();
					$branches[] = $implicitElseBranch;
				}
				
				// Merge all branches into parent scope
				$parentScope = $scopeStack->getCurrentScope();
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
