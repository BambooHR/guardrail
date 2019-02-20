<?php

namespace BambooHR\Guardrail\NodeVisitors;

use BambooHR\Guardrail\Checks\MethodCall;
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

	/** @var bool  */
	private $detectedDynamicScripting = false;

	/**
	 * @return void
	 */
	function reset() {
		$this->usedProperties = [];
		$this->detectedDynamicScripting = false;
	}

	/**
	 * @return array Key is property name, value = true
	 */
	function getUsedProperties() {
		return $this->usedProperties;
	}

	function detectedDynamicScripting() {
		return $this->detectedDynamicScripting;
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
			$node->var->name === 'this'
		) {
			if (is_string($node->name)) {
				$this->usedProperties[$node->name] = true;
			} else {
				// $this->$variable
				$this->detectedDynamicScripting = true;
			}
		}

		if ($node instanceof Node\Expr\FuncCall &&
			$node->name == "get_object_vars" &&
			count($node->args) >= 1 &&
			$node->args[0]->value instanceof Node\Expr\Variable &&
			($node->args[0]->value->name === 'this' || !($node->args[0]->value->name instanceof Node\Name))
		) {
			// get_object_vars($this), get_object_vars($$somevar))
			$this->detectedDynamicScripting = true;
		}
	}
}