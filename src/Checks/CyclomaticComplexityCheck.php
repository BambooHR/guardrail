<?php

namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Abstractions\ClassMethod;
use BambooHR\Guardrail\NodeVisitors\ForEachNode;
use PhpParser\Node;
use BambooHR\Guardrail\Scope;

class CyclomaticComplexityCheck extends BaseCheck
{
	/**
	 * @return string[]
	 */
	function getCheckNodeTypes() {
		return [ ClassMethod::class, Node\Stmt\Function_::class ];
	}

	/**
	 * @param Node $node
	 * @return bool
	 */
	private static function isExpressionBranch(Node $node) {
		return
			$node instanceof Node\Expr\Ternary ||
			$node instanceof Node\Expr\BinaryOp\LogicalOr ||
			$node instanceof Node\Expr\BinaryOp\LogicalAnd ||
			$node instanceof Node\Expr\BinaryOp\LogicalXor ||
			$node instanceof Node\Expr\BinaryOp\BooleanAnd ||
			$node instanceof Node\Expr\BinaryOp\BooleanOr;
	}

	/**
	 * @param Node $node
	 * @return bool
	 */
	private static function isStatementBranch(Node $node) {
		return
			$node instanceof Node\Stmt\Case_ ||
			$node instanceof Node\Stmt\Catch_ ||
			$node instanceof Node\Stmt\ElseIf_ ||
			$node instanceof Node\Stmt\For_ ||
			$node instanceof Node\Stmt\Foreach_ ||
			$node instanceof Node\Stmt\While_;
	}

	/**
	 * @param       $fileName
	 * @param Node  $node
	 * @param array $statements
	 * @return void
	 */
	function checkStatements($fileName, $name, Node $node, array $statements) {
		$complexity = 1;
		ForEachNode::run( $statements, function(Node $node) use(&$complexity) {
			if (self::isStatementBranch($node) || self::isExpressionBranch($node)) {
				++$complexity;
			}
		});
		if ($complexity>10) {
			$this->emitError($fileName, $node, ErrorConstants::TYPE_METRICS_COMPLEXITY, "Method ".$name." has a complexity of $complexity");
		}
	}


	/**
	 * @param string                   $fileName
	 * @param Node                     $node
	 * @param Node\Stmt\ClassLike|null $inside
	 * @param Scope|null               $scope
	 * @return void
	 */
	function run($fileName, Node $node, Node\Stmt\ClassLike $inside = null, Scope $scope = null) {
		if ($node instanceof Node\Stmt\ClassMethod) {
			$this->checkStatements($fileName, $node->name, $node, $node->stmts);
		}
		if ($node instanceof Node\Stmt\Function_) {
			$this->checkStatements($fileName, $node->name, $node, $node->stmts);
		}
	}
}