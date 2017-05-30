<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\NodeVisitors;

use PhpParser\Node;
use PhpParser\NodeTraverserInterface;
use PhpParser\NodeVisitor;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

class VariadicCheckVisitor extends NodeVisitorAbstract {
	private $foundVariatic = false;

	/**
	 * @return bool
	 */
	function getIsVariadic() {
		return $this->foundVariatic;
	}


	function enterNode(Node $node) {
		if ($node instanceof Node\FunctionLike) {
			return NodeTraverserInterface::DONT_TRAVERSE_CHILDREN;
		}

		if (
			$node instanceof Node\Expr\FuncCall &&
			$node->name instanceof Node\Name &&
			(
				strcasecmp(strval($node->name), "func_get_args") == 0 ||
				strcasecmp(strval($node->name), "func_num_args") == 0 ||
				strcasecmp(strval($node->name), "func_get_arg") == 0
			)
		) {
			$this->foundVariatic = true;
		}
	}

	static function isVariadic($stmts) {
		if (!is_array($stmts)) {
			return false;
		}
		$visitor = new self;
		$traverser = new NodeTraverser();
		$traverser->addVisitor($visitor);
		$traverser->traverse($stmts);
		return $visitor->getIsVariadic();
	}
}

