<?php

namespace BambooHR\Guardrail\Evaluators\Expression;

use BambooHR\Guardrail\Evaluators\ExpressionInterface;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeComparer;
use PhpParser\Node;

class ArrayDimFetch implements ExpressionInterface
{
	function getInstanceType(): array|string
	{
		return \PhpParser\Node\Expr\ArrayDimFetch::class;
	}

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): ?Node
	{
		/** @var Node\Expr\ArrayDimFetch $fetch */
		$fetch = $node;
		if ($fetch->dim == null) {
			return TypeComparer::identifierFromName("array");
		} else {
			return null; // Todo: inspect the variable
		}
	}
}