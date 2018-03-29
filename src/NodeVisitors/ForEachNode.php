<?php namespace BambooHR\Guardrail\NodeVisitors;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

/**
 * Class ForEachNode
 *
 * @package BambooHR\Guardrail\NodeVisitors
 */
class ForEachNode extends NodeVisitorAbstract {

	/**
	 * @var
	 */
	private $callBack;

	/**
	 * ForEachNode constructor.
	 *
	 * @param string $callBack The callback
	 */
	public function __construct($callBack) {
		$this->callBack = $callBack;
	}

	/**
	 * enterNode
	 *
	 * @param Node $node Instance of Node
	 *
	 * @return null
	 */
	public function enterNode(Node $node) {
		call_user_func( $this->callBack, $node );
		return null;
	}

	/**
	 * run
	 *
	 * @param array|null $nodes    Array of nodes
	 * @param callable   $callback Accepts Node, Return NULL to leave unchanged, Node to replace, or NodeTraverser::DONT_TRAVERSE_CHILDREN
	 *
	 * @return void
	 */
	public static function run(array $nodes = null, callable $callback) {
		if (is_array($nodes)) {
			$traverser = new NodeTraverser();
			$traverser->addVisitor(new self($callback));
			$traverser->traverse($nodes);
		}
	}
}