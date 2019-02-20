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
	/** @var array */
	private $usedProperties = [];

	/**
	 * @return void
	 */
	function reset() {
		$this->usedProperties = [];
	}

	/**
	 * @return array Key is property name, value = true
	 */
	function getUsedProperties() {
		return $this->usedProperties;
	}

	/**
	 * @param Node $node Any AST node
	 * @return null|int
	 */
	function enterNode(Node $node) {
		if ($node instanceof Node\Stmt\Class_) {
			// Don't look for usage in nested classes.
			return NodeTraverser::DONT_TRAVERSE_CHILDREN;
		}
		if ($node instanceof Node\Expr\PropertyFetch &&
			$node->var instanceof Node\Expr\Variable &&
			$node->var->name === 'this' &&
			is_string($node->name)
		) {
			$this->usedProperties[$node->name] = true;
		}
	}
}