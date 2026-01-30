<?php

namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\Util;
use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;

/**
 * Class UnreachableCodeCheck
 *
 * @package BambooHR\Guardrail\Checks
 */
class UnreachableCodeCheck extends BaseCheck {

	/**
	 * getCheckNodeTypes
	 *
	 * @return string[]
	 */
	function getCheckNodeTypes() {
		return [ Function_::class, ClassMethod::class ];
	}

	/**
	 * run
	 *
	 * @param string         $fileName The name of the file we are parsing
	 * @param Node           $node     Instance of the Node
	 * @param ClassLike|null $inside   Instance of the ClassLike (the class we are parsing) [optional]
	 * @param Scope|null     $scope    Instance of the Scope (all variables in the current state) [optional]
	 *
	 * @return void
	 */
	public function run($fileName, Node $node, ?ClassLike $inside = null, ?Scope $scope = null) {
		if ($node instanceof Function_ || $node instanceof ClassMethod) {
			$statements = [];
			if ($node instanceof FunctionLike) {
				$statements = $node->getStmts();
				if (!is_array($statements)) {
					$statements = [$statements];
				}
			}
			$statement = $this->checkForUnreachableNode($statements);
			if (null !== $statement) {
				if ($statement->getLine() > 0) {
					$this->emitError($fileName, $statement, ErrorConstants::TYPE_UNREACHABLE_CODE, "Unreachable code was found.");
					return;
				}
			}
		}
	}

	/**
	 * checkForUnreachableNode
	 *
	 * @param array $statements An array of statements from the node
	 *
	 * @return mixed|null
	 */
	public function checkForUnreachableNode(array $statements) {
		do {
			$previous = array_shift($statements);
		} while ( $previous instanceof Node\Stmt\Nop);
		foreach ($statements as $statement) {
			if (!$statement instanceof Node\Stmt\Nop)
				if (Util::allBranchesExit([$previous])) {
					return $statement;
				} else {
					$previous = $statement;
				}
		}
		return null;
	}
}