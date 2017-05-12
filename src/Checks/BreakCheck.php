<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */


namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Scope;
use PhpParser\Node;
use PhpParser\Node\Stmt\Break_;

class BreakCheck extends BaseCheck {

	function getCheckNodeTypes() {
		return [ Break_::class, Node\Stmt\Continue_::class ];
	}

	function run($fileName, $node, Node\Stmt\ClassLike $inside = null, Scope $scope = null) {
		if ($node->num!=null) {
			$name = $node instanceof Break_ ? "break" : "continue";
			$this->emitError($fileName, $node, BaseCheck::TYPE_BREAK_NUMBER, "Usage of unsafe \"$name [expression]\" form");
		}
	}
}