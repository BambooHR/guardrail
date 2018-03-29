<?php namespace BambooHR\Guardrail\NodeVisitors;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Name;
use PhpParser\NodeTraverserInterface;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

/**
 * Class VariadicCheckVisitor
 *
 * @package BambooHR\Guardrail\NodeVisitors
 */
class VariadicCheckVisitor extends NodeVisitorAbstract {

	/**
	 * @var bool
	 */
	private $foundVariatic = false;

	/**
	 * @return bool
	 */
	public function getIsVariadic() {
		return $this->foundVariatic;
	}

	/**
	 * enterNode
	 *
	 * @param Node $node Instance of Node
	 *
	 * @return int
	 */
	public function enterNode(Node $node) {
		if ($node instanceof FunctionLike) {
			return NodeTraverser::DONT_TRAVERSE_CHILDREN;
		}

		if (
			$node instanceof FuncCall &&
			$node->name instanceof Name &&
			(
				strcasecmp(strval($node->name), "func_get_args") == 0 ||
				strcasecmp(strval($node->name), "func_num_args") == 0 ||
				strcasecmp(strval($node->name), "func_get_arg") == 0
			)
		) {
			$this->foundVariatic = true;
		}
	}

	/**
	 * isVariadic
	 *
	 * @param array $stmts The list of statements
	 *
	 * @return bool
	 */
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

