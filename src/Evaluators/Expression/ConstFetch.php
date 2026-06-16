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

	function getType(SymbolTable $table, Node\Expr\ConstFetch $expr): ?Node\Identifier {
		if (strcasecmp($expr->name, "null") == 0) {
			return TypeComparer::identifierFromName("null");
		}
		if (strcasecmp($expr->name, "false") == 0 || strcasecmp($expr->name, "true") == 0) {
			return TypeComparer::identifierFromName($expr->name);
		}
		if (defined($expr->name)) {
			// Guardrail doesn't declare any global constants.  Any that exist are from the runtime.
			// Infer the type from the actual constant value
			$value = constant($expr->name);
			return $this->getTypeFromValue($value);
		}
		if ($table->isDefined($expr->name)) {
			return TypeComparer::identifierFromName("mixed");
		}
		return TypeComparer::identifierFromName("mixed");
	}

	/**
	 * Infer type from a constant's runtime value
	 *
	 * Note: boolean and NULL types are handled earlier in getType() and won't reach this method.
	 * array, resource, and object types are kept for defensive programming but are rarely
	 * encountered in PHP runtime constants.
	 *
	 * @codeCoverageIgnore
	 */
	private function getTypeFromValue($value): Node\Identifier {
		$type = gettype($value);
		switch ($type) {
			case 'boolean':
				return TypeComparer::identifierFromName('bool');
			case 'integer':
				return TypeComparer::identifierFromName('int');
			case 'double':
				return TypeComparer::identifierFromName('float');
			case 'string':
				return TypeComparer::identifierFromName('string');
			case 'array':
				return TypeComparer::identifierFromName('array');
			case 'NULL':
				return TypeComparer::identifierFromName('null');
			case 'resource':
			case 'resource (closed)':
				return TypeComparer::identifierFromName('resource');
			case 'object':
				return TypeComparer::identifierFromName(get_class($value));
			default:
				return TypeComparer::identifierFromName('mixed');
		}
	}
}
