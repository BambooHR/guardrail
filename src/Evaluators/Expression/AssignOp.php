<?php

namespace BambooHR\Guardrail\Evaluators\Expression;

use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeComparer;
use PhpParser\Node;

class AssignOp implements \BambooHR\Guardrail\Evaluators\ExpressionInterface
{
	function getInstanceType(): array|string {
		return Node\Expr\AssignOp::class;
	}

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): ?Node {
		/** @var Node\Expr\AssignOp $assignOp */
		$assignOp = $node;
		return $this->getType($assignOp);
	}

	function getType(Node\Expr\AssignOp $assignOp) {
		if ($assignOp instanceof Node\Expr\AssignOp\Concat) {
			return TypeComparer::identifierFromName("string");
		}
		return null;
	}
}