<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\Checks;

use PhpParser\Node\Stmt\ClassLike;
use BambooHR\Guardrail\Checks\BaseCheck;
use BambooHR\Guardrail\Scope;

class BacktickOperatorCheck extends BaseCheck
{
	function getCheckNodeTypes() {
		return [\PhpParser\Node\Expr\ShellExec::class];
	}

	/**
	 * @param string $fileName
	 * @param \PhpParser\Node\Stmt\Catch_ $node
	 */
	function run($fileName, $node, ClassLike $inside=null, Scope $scope=null) {
		$this->incTests();
		$this->emitError($fileName,$node,self::TYPE_SECURITY_BACKTICK, "Unsafe operator (backtick)");
	}
}