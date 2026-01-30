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
		$closureScope = $scopeStack->popScope();

		if ($node instanceof Node\Expr\Closure) {
			$uses = array_map(
				fn(Node\Expr\ClosureUse $closureUse):string => $closureUse->var->name,
				$node->uses
			);
			foreach ($closureScope->getUsedVars() as $var) {
				/** @var Scope\ScopeVar $var */
				if ($scopeStack->getVarExists($var->name) && $var->used && in_array($var->name, $uses)) {
					$scopeStack->setVarUsed($var->name);
				}
			}
		} else if ($node instanceof Node\Expr\ArrowFunction) {
			foreach ($closureScope->getUsedVars() as $var) {
				/** @var Scope\ScopeVar $var */
				if ($scopeStack->getVarExists($var->name) && $var->used) {
					$scopeStack->setVarUsed($var->name);
				}
			}
		}
		return TypeComparer::identifierFromName("closure");
	}
}