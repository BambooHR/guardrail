<?php

namespace BambooHR\Guardrail\Evaluators\Expression;

use BambooHR\Guardrail\Evaluators\ExpressionInterface;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeComparer;
use PhpParser\Node;

class ConstFetch implements ExpressionInterface
{
	function getInstanceType(): string {
		return Node\Expr\ConstFetch::class;
	}

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): ?Node {
		/** @var Node\Expr\ConstFetch $constFetch */
		$constFetch = $node;
		return $this->getType($table, $constFetch);
	}

	function getType(SymbolTable $table, Node\Expr\ConstFetch $expr):?Node\Identifier {
		if (strcasecmp($expr->name, "null") == 0) {
			return TypeComparer::identifierFromName("null");
		}
		if (strcasecmp($expr->name, "false") == 0 || strcasecmp($expr->name, "true") == 0) {
			return TypeComparer::identifierFromName($expr->name);
		}
		if (defined($expr->name)) {
			// Infer type from the actual constant value
			$value = constant($expr->name);
			if (is_int($value)) {
				return TypeComparer::identifierFromName("int");
			} elseif (is_float($value)) {
				return TypeComparer::identifierFromName("float");
			} elseif (is_string($value)) {
				return TypeComparer::identifierFromName("string");
			} elseif (is_bool($value)) {
				return TypeComparer::identifierFromName("bool");
			} elseif (is_array($value)) {
				return TypeComparer::identifierFromName("array");
			}
			return TypeComparer::identifierFromName("mixed");
		}
		if ($table->isDefined($expr->name)) {
			return TypeComparer::identifierFromName("mixed");
		}
		return TypeComparer::identifierFromName("mixed");
	}
}