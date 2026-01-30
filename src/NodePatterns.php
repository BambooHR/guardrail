<?php

namespace BambooHR\Guardrail;

use PhpParser\Node;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\Variable;

class NodePatterns
{

	public static function getVariableOrPropertyName($node): ?string {
		if ($node instanceof Variable && is_string($node->name)) {
			return $node->name;
		} else {
			return TypeComparer::getChainedPropertyFetchName($node);
		}
	}


	public static function parentIgnoresNulls(array $parentNodes, Node $child): bool {
		foreach ($parentNodes as $node) {
			if ($node instanceof Node\Expr\Isset_ ||
				$node instanceof Node\Expr\Empty_ ||
				($node instanceof Node\Expr\BinaryOp\Coalesce && $node->left === $child) ||
				($node instanceof Node\Expr\Assign && $node->var === $child)
			) {
				return true;
			}
		}
		return false;
	}

	public static function parentNodeExpectsBool(Node $parent, Node $child): bool {
		return (
			$parent instanceof Node\Expr\BinaryOp\BooleanAnd ||
			$parent instanceof Node\Expr\BinaryOp\BooleanOr ||
			$parent instanceof Node\Stmt\If_ ||
			($parent instanceof Node\Expr\Ternary && $parent->cond == $child)
		);
	}

}