<?php

namespace BambooHR\Guardrail\NodeVisitors;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

/**
 * Class PropertyUsageVisitor
 *
 * Creates a list of used properties of the for $this->prop in a list of statements.
 * @package BambooHR\Guardrail\NodeVisitors
 */

class PropertyUsageVisitor extends NodeVisitorAbstract {

	private $usedProperties = [];

	function reset() {
		$this->usedProperties = [];
	}

	function getUsedProperties() {
		return $this->usedProperties;
	}

	function enterNode(Node $node) {
		if ($node instanceof Node\Stmt\Class_) {
			// Don't look for usage in nested classes.
			return NodeTraverser::DONT_TRAVERSE_CHILDREN;
		}
		if ($node instanceof Node\Expr\PropertyFetch &&
			$node->var instanceof Node\Expr\Variable &&
			is_string($node->var->name) &&
			$node->var->name === 'this' &&
			is_string($node->name)
		) {
			$usedVariables[$node->name] = true;
		}
	}
}