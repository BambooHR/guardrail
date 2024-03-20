<?php

namespace BambooHR\Guardrail\Evaluators\Expression;

use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeComparer;
use PhpParser\Node;

class IncDec implements \BambooHR\Guardrail\Evaluators\ExpressionInterface
{

	function getInstanceType(): array|string
	{
		return [Node\Expr\PreDec::class, Node\Expr\PreInc::class, Node\Expr\PostInc::class, Node\Expr\PostDec::class];
	}

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): ?Node
	{
		return $node->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);
	}
}