<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */


namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Scope;
use PhpParser\Node;
use PhpParser\Node\Stmt\Break_;

class GotoCheck extends BaseCheck {

	function getCheckNodeTypes() {
		return [ Node\Stmt\Goto_::class ];
	}

	function run($fileName, $node, Node\Stmt\ClassLike $inside = null, Scope $scope = null) {
		$this->emitError($fileName, $node, BaseCheck::TYPE_GOTO, "Usage of goto command");
	}
}