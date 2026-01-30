<?php 

namespace BambooHR\Guardrail\Checks;

/**
 * Guardrail.  Copyright (c) 2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\NodeVisitors\ForEachNode;
use BambooHR\Guardrail\Scope;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;

/**
 * Class ConstructorCheck
 *
 * @package BambooHR\Guardrail\Checks
 */
class ConditionalAssignmentCheck extends BaseCheck {

	/**
	 * getCheckNodeTypes
	 *
	 * @return array
	 */
	public function getCheckNodeTypes() {
		return [Node\Stmt\If_::class];
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
		if ($node instanceof Node\Stmt\If_) {
			$assignment = null;
			ForEachNode::run([$node->cond], function($node) use (&$assignment) {
				if ($node instanceof Node\Expr\Assign) {
					$assignment = $node;
				}
			});
			if ($assignment) {
				$this->emitError($fileName, $assignment, ErrorConstants::TYPE_CONDITIONAL_ASSIGNMENT, "Attempt to assign a variable inside an if() condition clause");
			}
		}
	}
}