<?php

// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps

namespace BambooHR\Guardrail\Evaluators;

use BambooHR\Guardrail\Scope\Scope;
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
			// Store parent scope snapshot BEFORE condition evaluation
			$parentScope = $scopeStack->getCurrentScope();
			$parentSnapshot = $parentScope->getScopeClone();
			$node->setAttribute('if-parent-scope', $parentSnapshot);
			$node->setAttribute('if-pre-condition-scope', $parentSnapshot);
			
			// Set reference to this if node on all elseif and else nodes
			foreach ($node->elseifs as $elseIf) {
				assert($elseIf instanceof ElseIf_);
				$elseIf->setAttribute('parent-if', $node);
			}
			if ($node->else instanceof Node\Stmt\Else_) {
				$node->else->setAttribute('parent-if', $node);
			}
			
			// Push un-narrowed scope for condition evaluation.
			// Narrowing will be applied AFTER the condition is evaluated
			// (via the 'if-narrow-on-leave' attribute on the cond node)
			// to avoid pre-narrowing the scope before checks run on the condition.
			$condScope = $parentSnapshot->getScopeClone();
			$scopeStack->pushScope($condScope);
			
			// Mark condition to apply narrowing when it finishes evaluating
			$node->cond->setAttribute('if-narrow-on-leave', $node);
			
		} elseif ($node instanceof Node\Stmt\ElseIf_) {
			// ElseIf nodes are children of the If_ node, so we need to find it
			// The parent scope is stored on the If_ node
			// For now, we'll store a reference to the if node on each elseif
			// This will be set when we process the If_ node
			$ifNode = $node->getAttribute('parent-if');
			if ($ifNode) {
				// Pop the previous branch scope and update its attribute with the executed scope
				$executedScope = $scopeStack->popScope();
				
				// Determine which branch we're coming from and update its attribute
				$elseifIndex = $node->getAttribute('elseif-index');
				if ($elseifIndex === null || $elseifIndex === 0) {
					// Coming from then-branch
					$ifNode->setAttribute('if-then-scope', $executedScope);
				} else {
					// Coming from a previous elseif
					$prevElseIf = $ifNode->elseifs[$elseifIndex - 1];
					if ($prevElseIf) {
						$prevElseIf->setAttribute('elseif-scope', $executedScope);
					}
				}
				
				/** @var Scope $parentScope */
				$parentScope = $ifNode->getAttribute('if-parent-scope');
				
				// Create independent scope for elseif-branch from parent
				/** @var Scope $elseIfBranch */
				$elseIfBranch = $parentScope->getScopeClone();
				
				// Apply inverse narrowing from the if condition (it was false to reach here)
				TypeAssertion::narrowTypes($ifNode->cond, $elseIfBranch, false);
				
				// Apply inverse narrowing from all previous elseif conditions
				$currentElseifIndex = $node->getAttribute('elseif-index') ?? 0;
				for ($i = 0; $i < $currentElseifIndex; $i++) {
					if (isset($ifNode->elseifs[$i])) {
						TypeAssertion::narrowTypes($ifNode->elseifs[$i]->cond, $elseIfBranch, false);
					}
				}
				
				// Apply narrowing for this elseif's own condition (it must be true)
				TypeAssertion::narrowTypes($node->cond, $elseIfBranch, true);
				
				// Store on the elseif node and push to stack
				$node->setAttribute('elseif-scope', $elseIfBranch);
				$scopeStack->pushScope($elseIfBranch);
			}
			
		} elseif ($node instanceof Node\Stmt\Else_) {
			// Else node is a child of the If_ node
			$ifNode = $node->getAttribute('parent-if');
			if ($ifNode) {
				// Pop the previous branch scope and update its attribute with the executed scope
				$executedScope = $scopeStack->popScope();
				
				// Determine which branch we're coming from and update its attribute
				if (count($ifNode->elseifs) > 0) {
					// Coming from last elseif
			
					/** @var Node\Stmt\ElseIf_ $lastElseIf */
					$lastElseIf = $ifNode->elseifs[count($ifNode->elseifs) - 1];
					$lastElseIf->setAttribute('elseif-scope', $executedScope);
				} else {
					// Coming from then-branch
					$ifNode->setAttribute('if-then-scope', $executedScope);
				}
				
				/** @var \BambooHR\Guardrail\Scope\Scope $parentScope */
				$parentScope = $ifNode->getAttribute('if-parent-scope');
				
				// Create independent scope for else-branch from parent
				$elseBranch = $parentScope->getScopeClone();
				
				// Apply inverse narrowing from if and all elseif conditions
				TypeAssertion::narrowTypes($ifNode->cond, $elseBranch, false);
				foreach ($ifNode->elseifs as $elseIf) {
					assert($elseIf instanceof Node\Stmt\ElseIf_);
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
			} else {
				// Collect all branch scopes from node attributes (independent clones)
				$branches = [];
				$exitedBranches = [];
				
				// Pop the last branch from stack and update its attribute
				$lastBranchScope = $scopeStack->popScope();
				
				// Update the attribute for the last branch
				if ($node->else !== null) {
					// Last branch is else
					$node->else->setAttribute('else-scope', $lastBranchScope);
				} elseif (count($node->elseifs) > 0) {
					// Last branch is last elseif
					$lastElseIf = $node->elseifs[count($node->elseifs) - 1];
					assert($lastElseIf instanceof Node\Stmt\ElseIf_);
					$lastElseIf->setAttribute('elseif-scope', $lastBranchScope);
				} else {
					// Last branch is then
					$node->setAttribute('if-then-scope', $lastBranchScope);
				}
				
				// Add then-branch scope from node attribute
				$thenScope = $node->getAttribute('if-then-scope');
				$branches[] = $thenScope;
				if ($thenExitsEarly) {
					$exitedBranches[] = 0;
				}
				
				// Add elseif branches from node attributes
				for ($i = 0; $i < count($node->elseifs); ++$i) {
					$elseIfNode = $node->elseifs[$i];
					assert($elseIfNode instanceof ElseIf_);	
					// Note: elseif scopes were already popped in onEnter when transitioning to next branch
					
					/** @var Scope $elseIfScope */
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
					// Note: else scope was already popped above (it's the last branch)
					
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
				/** @var Scope $parentScope */
				$parentScope = $node->getAttribute('if-parent-scope');
				$implicitElseBranch = $parentScope->getScopeClone();
				
				// Apply inverse narrowing from the if condition (it was false to reach implicit else)
				TypeAssertion::narrowTypes($node->cond, $implicitElseBranch, false);
				
				// Apply inverse narrowing from all elseif conditions (they were all false)
				foreach ($node->elseifs as $elseIfNode) {
					TypeAssertion::narrowTypes($elseIfNode->cond, $implicitElseBranch, false);
				}
				
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
		if ($cond && $cond->hasAttribute('assertsTrue')) {
			$scopeStack->popScope();
			$condScope = $cond->getAttribute('assertsTrue');
			$scopeStack->pushScope($condScope);
		}
		$scopeStack->swapTopTwoScopes();
		if ($cond && $cond->hasAttribute('assertsFalse')) {
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
