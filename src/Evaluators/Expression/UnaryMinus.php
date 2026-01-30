<?php

namespace BambooHR\Guardrail\Evaluators\Expression;

use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeComparer;
use PhpParser\Node;

class UnaryMinus implements \BambooHR\Guardrail\Evaluators\ExpressionInterface
{

	function getInstanceType(): array|string {
		return [Node\Expr\UnaryMinus::class,Node\Expr\BitwiseNot::class, Node\Expr\UnaryPlus::class];
	}

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): ?Node {
		/** @var Node\Expr\UnaryMinus $minus */
		$minus = $node;
		$type = $minus->expr->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);
		if ($node instanceof UnaryMinus || $node instanceof Node\Expr\UnaryPlus) {
			if (TypeComparer::isNamedIdentifier($type, "int") || TypeComparer::isNamedIdentifier($type, "float")) {
				return $type;
			}
			return new Node\UnionType([TypeComparer::identifierFromName("int"), TypeComparer::identifierFromName("float")]);
		} else if ($node instanceof Node\Expr\BitwiseNot) {
			return TypeComparer::identifierFromName("int");
		}
		return null;
	}
}