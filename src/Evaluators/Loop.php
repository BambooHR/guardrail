<?php

namespace BambooHR\Guardrail\Evaluators;

use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeInference\TypeAssertion;
use PhpParser\Node;

class Loop implements OnEnterEvaluatorInterface, OnExitEvaluatorInterface {
	function getInstanceType(): array|string {
		return [Node\Stmt\While_::class, Node\Stmt\Do_::class];
	}

	function onEnter(Node $node, SymbolTable $table, ScopeStack $scopeStack): void {
		if ($node instanceof Node\Stmt\While_) {
			// Store parent scope for post-loop inverse narrowing
			$node->setAttribute('loop-parent-scope', $scopeStack->getCurrentScope());
			
			// Create scope for loop body
			$loopScope = $scopeStack->getCurrentScope()->getScopeClone();
			
			// Apply type narrowing for truthy condition (inside loop)
			TypeAssertion::narrowTypes($node->cond, $loopScope, true);
			
			$scopeStack->pushScope($loopScope);
		}

		if ($node instanceof Node\Stmt\Do_) {
			// Store parent scope for post-loop inverse narrowing
			$node->setAttribute('loop-parent-scope', $scopeStack->getCurrentScope());
			
			// Loop body executes at least once
			$loopScope = $scopeStack->getCurrentScope()->getScopeClone();
			$scopeStack->pushScope($loopScope);
		}
	}

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): void {
		if ($node instanceof Node\Stmt\While_) {
			// Pop loop scope
			$loopScope = $scopeStack->popScope();
			
			// Get parent scope
			$parentScope = $scopeStack->getCurrentScope();
			
			// Merge loop scope into parent (variables modified in loop)
			$parentScope->merge($loopScope);
			
			// Apply INVERSE narrowing after loop
			// If loop condition was "if ($x)", after loop we know "!$x" is true
			TypeAssertion::narrowTypes($node->cond, $parentScope, false);
		}

		if ($node instanceof Node\Stmt\Do_) {
			// Pop loop scope
			$loopScope = $scopeStack->popScope();
			
			// Get parent scope
			$parentScope = $scopeStack->getCurrentScope();
			
			// Merge loop scope into parent
			$parentScope->merge($loopScope);
			
			// Apply inverse narrowing from condition
			// After do-while, the condition was false to exit
			TypeAssertion::narrowTypes($node->cond, $parentScope, false);
		}
	}
}
