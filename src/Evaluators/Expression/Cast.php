<?php

namespace BambooHR\Guardrail\Evaluators\Expression;

use BambooHR\Guardrail\Evaluators\ExpressionInterface;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeComparer;
use InvalidArgumentException;
use PhpParser\Node;
use PhpParser\Node\Expr\Cast as CastExp;

class Cast implements ExpressionInterface {
	function getInstanceType(): string {
		return Node\Expr\Cast::class;
	}

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): ?Node {
		$expr = $this->lookupCastType($node);
		if ($expr) {
			return $expr;
		}
		throw new  InvalidArgumentException("Unknown cast type " . get_class($node));
	}


	function lookupCastType($expr): ?Node\Identifier {
		if ($expr instanceof CastExp\Int_) {
			return TypeComparer::identifierFromName("int");
		}
		if ($expr instanceof CastExp\Double) {
			return TypeComparer::identifierFromName("float");
		}

		if ($expr instanceof CastExp\String_) {
			return TypeComparer::identifierFromName("string");
		}
		if ($expr instanceof CastExp\Bool_) {
			return TypeComparer::identifierFromName("bool");
		}
		if ($expr instanceof CastExp\Array_) {
			return TypeComparer::identifierFromName("array");
		}
		if ($expr instanceof CastExp\Object_) {
			return TypeComparer::identifierFromName("object");
		}
		return null;
	}
}