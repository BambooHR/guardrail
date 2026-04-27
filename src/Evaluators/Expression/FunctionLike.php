<?php

namespace BambooHR\Guardrail\Evaluators\Expression;

use BambooHR\Guardrail\Evaluators\ExpressionInterface;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeComparer;
use PhpParser\Node;

class FunctionLike implements ExpressionInterface
{
	function getInstanceType(): array {
		return [Node\Expr\ArrowFunction::class, Node\Expr\Closure::class];
	}

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): ?Node {
		\BambooHR\Guardrail\Evaluators\FunctionLike::handleUnusedVars($scopeStack);
		$scopeStack->popScope();

		// Use the function-scope attribute directly rather than the popped top-of-stack scope.
		// When the function body contains a ternary, scope branching can leave a cloned scope
		// (with stale `used` flags) on top of the stack instead of the original function-scope.
		// The function-scope attribute is always written to by setVarUsed(), so it is the
		// authoritative source for which variables were actually referenced.
		$fnScope = $node->getAttribute('function-scope');

		if ($node instanceof Node\Expr\Closure) {
			$uses = array_map(
				fn(Node\Expr\ClosureUse $closureUse): string => $closureUse->var->name,
				$node->uses
			);
			foreach ($fnScope->getUsedVars() as $var) {
				/** @var Scope\ScopeVar $var */
				if ($scopeStack->getVarExists($var->name) && $var->used && in_array($var->name, $uses)) {
					$scopeStack->setVarUsed($var->name);
				}
			}
		} elseif ($node instanceof Node\Expr\ArrowFunction) {
			foreach ($fnScope->getUsedVars() as $var) {
				/** @var Scope\ScopeVar $var */
				if ($scopeStack->getVarExists($var->name) && $var->used) {
					$scopeStack->setVarUsed($var->name);
				}
			}
		}
		return TypeComparer::identifierFromName("closure");
	}
}
