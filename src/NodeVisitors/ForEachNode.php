<?php
/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\NodeVisitors;


use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

class ForEachNode extends NodeVisitorAbstract {
	private $callBack;

	function __construct($callBack) {
		$this->callBack = $callBack;
	}

	function enterNode(Node $node) {
		call_user_func( $this->callBack, $node );
		return null;
	}

	public static function run(array $nodes=null, callable $callback) {
		if (is_array($nodes)) {
			$traverser = new NodeTraverser();
			$traverser->addVisitor(new self($callback));
			$traverser->traverse($nodes);
		}
	}
}