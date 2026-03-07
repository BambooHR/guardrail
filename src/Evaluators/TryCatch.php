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
			// Store parent scope for merging
			$parentScope = $scopeStack->getCurrentScope();
			$node->setAttribute('try-parent-scope', $parentScope);
			$node->setAttribute('try-parent-vars', array_keys($parentScope->getTypeChangedVars()));
			$node->setAttribute('try-catch-scopes', []);
			
			// Create scope for try block
			$tryScope = $parentScope->getScopeClone();
			$scopeStack->pushScope($tryScope);
		}

		if ($node instanceof Node\Stmt\Catch_) {
			$parentTry = $this->findParentTry($node);
			if (!$parentTry) {
				return;
			}
			
			// Pop previous scope (try or previous catch)
			$previousScope = $scopeStack->popScope();
			
			// Store the previous scope
			$scopes = $parentTry->getAttribute('try-catch-scopes');
			$scopes[] = $previousScope;
			$parentTry->setAttribute('try-catch-scopes', $scopes);
			
			// Create scope for this catch block from parent (not from try)
			$parentScope = $parentTry->getAttribute('try-parent-scope');
			$catchScope = $parentScope->getScopeClone();
			
			// Add exception variable to catch scope
			if ($node->var) {
				$catchScope->setVarType($node->var->name, $node->type, $node->getLine());
				$catchVar = $catchScope->getVarObject($node->var->name);
				if ($catchVar) {
					$catchVar->mayBeNull = false;
					$catchVar->mayBeUnset = false;
				}
			}
			
			$scopeStack->pushScope($catchScope);
		}

		if ($node instanceof Node\Stmt\Finally_) {
			$parentTry = $this->findParentTry($node);
			if (!$parentTry) {
				return;
			}
			
			// Pop last catch scope (or try if no catches)
			$previousScope = $scopeStack->popScope();
			
			// Store the previous scope
			$scopes = $parentTry->getAttribute('try-catch-scopes');
			$scopes[] = $previousScope;
			$parentTry->setAttribute('try-catch-scopes', $scopes);
			
			// Create scope for finally block from parent
			$parentScope = $parentTry->getAttribute('try-parent-scope');
			$finallyScope = $parentScope->getScopeClone();
			$scopeStack->pushScope($finallyScope);
			
			$parentTry->setAttribute('has-finally', true);
		}
	}

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): void {
		if ($node instanceof Node\Stmt\TryCatch) {
			$parentScope = $scopeStack->getCurrentScope();
			$parentVars = $node->getAttribute('try-parent-vars') ?? [];
			$scopes = $node->getAttribute('try-catch-scopes');
			$hasFinally = $node->getAttribute('has-finally') ?? false;
			
			// Pop the current scope (last catch, try, or finally)
			$currentScope = $scopeStack->popScope();
			
			if ($hasFinally) {
				// Finally scope - variables here are guaranteed to be set
				// Merge finally scope into parent
				$parentScope->merge($currentScope);
				
				// Now merge try/catch scopes with mayBeUnset for new variables
				$this->mergeTryCatchScopes($parentScope, $scopes, $parentVars);
			} else {
				// No finally - add current scope to list and merge all
				$scopes[] = $currentScope;
				$this->mergeTryCatchScopes($parentScope, $scopes, $parentVars);
			}
		}
	}

	/**
	 * Merge try/catch scopes, marking new variables as mayBeUnset
	 */
	private function mergeTryCatchScopes(Scope $parentScope, array $scopes, array $parentVars): void {
		// Collect all variables from all try/catch scopes
		$allVars = [];
		foreach ($scopes as $scope) {
			foreach ($scope->getTypeChangedVars() as $name => $var) {
				if (!in_array($name, $parentVars, true)) {
					// This is a new variable declared in try/catch
					$allVars[$name] = true;
				}
			}
		}
		
		// For each new variable, merge types and mark as mayBeUnset
		foreach (array_keys($allVars) as $varName) {
			$types = [];
			$existsInAllScopes = true;
			$mayBeNullInAny = false;
			
			foreach ($scopes as $scope) {
				if ($scope->getVarExists($varName)) {
					$scopeVar = $scope->getVarObject($varName);
					if ($scopeVar) {
						$types[] = $scopeVar->type;
						$mayBeNullInAny = $mayBeNullInAny || $scopeVar->mayBeNull;
					}
				} else {
					$existsInAllScopes = false;
				}
			}
			
			// Union all types
			if (!empty($types)) {
				$mergedType = $this->unionTypes($types);
				
				// Set variable in parent scope
				if (!$parentScope->getVarExists($varName)) {
					$parentScope->setVarType($varName, $mergedType, 0);
				}
				
				$parentVar = $parentScope->getVarObject($varName);
				if ($parentVar) {
					// Variables declared in try/catch are ALWAYS mayBeUnset
					// because any line could have thrown
					$parentVar->mayBeUnset = true;
					$parentVar->mayBeNull = $mayBeNullInAny;
					$parentVar->typeChanged = true;
				}
			}
		}
		
		// Also merge variables that existed before but were modified
		foreach ($scopes as $scope) {
			foreach ($scope->getTypeChangedVars() as $name => $var) {
				if (in_array($name, $parentVars, true)) {
					// This variable existed before - normal merge
					$parentScope->merge($scope);
					break; // Only need to merge once
				}
			}
		}
	}

	/**
	 * Union multiple types together
	 */
	private function unionTypes(array $types): Node\Name|Node\Identifier|Node\ComplexType|null {
		$types = array_filter($types, fn($t) => $t !== null);
		
		if (empty($types)) {
			return null;
		}
		
		if (count($types) === 1) {
			return reset($types);
		}
		
		// Flatten union types
		$flatTypes = [];
		foreach ($types as $type) {
			if ($type instanceof Node\ComplexType && $type instanceof Node\UnionType) {
				foreach ($type->types as $subType) {
					$flatTypes[] = $subType;
				}
			} else {
				$flatTypes[] = $type;
			}
		}
		
		// Remove duplicates
		$uniqueTypes = [];
		$seenTypes = [];
		foreach ($flatTypes as $type) {
			$typeStr = \BambooHR\Guardrail\TypeComparer::typeToString($type);
			if (!isset($seenTypes[$typeStr])) {
				$seenTypes[$typeStr] = true;
				$uniqueTypes[] = $type;
			}
		}
		
		if (count($uniqueTypes) === 1) {
			return $uniqueTypes[0];
		}
		
		return new Node\UnionType($uniqueTypes);
	}

	/**
	 * Find parent try/catch statement
	 */
	private function findParentTry(Node $node): ?Node\Stmt\TryCatch {
		$current = $node;
		while ($current = $current->getAttribute('parent')) {
			if ($current instanceof Node\Stmt\TryCatch) {
				return $current;
			}
		}
		return null;
	}
}
