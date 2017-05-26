<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */


namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Scope;
use PhpParser\Node;
use PhpParser\Node\Stmt\Break_;
use PhpParser\Node\Stmt\ClassLike;

class BreakCheck extends BaseCheck {

	function getCheckNodeTypes() {
		return [ Break_::class, Node\Stmt\Continue_::class ];
	}

	/**
	 * run
	 *
	 * @param string         $fileName The name of the file we are parsing
	 * @param Node           $node     Instance of the Node
	 * @param ClassLike|null $inside   Instance of the ClassLike (the class we are parsing) [optional]
	 * @param Scope|null     $scope    Instance of the Scope (all variables in the current state) [optional]
	 *
	 * @return mixed
	 */
	public function run($fileName, Node $node, ClassLike $inside = null, Scope $scope = null) {
		if ($node->num != null) {
			$name = $node instanceof Break_ ? "break" : "continue";
			$this->emitError($fileName, $node, ErrorConstants::TYPE_BREAK_NUMBER, "Usage of unsafe \"$name [expression]\" form");
		}
	}
}