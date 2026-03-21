<?php

// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps

namespace BambooHR\Guardrail\Evaluators\Expression;

use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeComparer;
use PhpParser\Node;

class Match_ implements \BambooHR\Guardrail\Evaluators\ExpressionInterface
{
	function getInstanceType(): array|string {

		return Node\Expr\Match_::class;
	}

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): ?Node {
		/** @var Node\Expr\Match_ $match */
		$match = $node;
		
		// Collect types from all match arms
		$types = [];
		$hasDefault = false;
		
		foreach ($match->arms as $arm) {
			// Check if this is a default arm (null conditions)
			if ($arm->conds === null) {
				$hasDefault = true;
			}
			
			// Get the inferred type from the arm's body
			$armType = $arm->body->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);
			if ($armType !== null) {
				$types[] = $armType;
			}
		}
		
		// If we have no types, return null (unknown)
		if (empty($types)) {
			return null;
		}
		
		// Merge all arm types
		$resultType = TypeComparer::getUniqueTypes(...$types);
		
		// If there's no default arm, the match could throw UnhandledMatchError
		// So we should add null to indicate it might not return a value
		// But if all arms return non-null and there IS a default, result is non-null
		if (!$hasDefault) {
			// No default means match could fail at runtime, but in practice
			// PHP throws UnhandledMatchError, so we keep the merged type as-is
			// The developer is responsible for ensuring all cases are covered
		}
		
		return $resultType;
	}
}
