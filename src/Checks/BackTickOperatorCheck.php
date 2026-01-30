<?php 

namespace BambooHR\Guardrail\Checks;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Scope;
use PhpParser\Node;
use PhpParser\Node\Expr\ShellExec;
use PhpParser\Node\Stmt\ClassLike;

/**
 * Class BackTickOperatorCheck
 *
 * @package BambooHR\Guardrail\Checks
 */
class BackTickOperatorCheck extends BaseCheck {

	/**
	 * getCheckNodeTypes
	 *
	 * @return string[]
	 */
	public function getCheckNodeTypes() {
		return [ShellExec::class];
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
		if ($node instanceof ShellExec) {
			$this->emitError($fileName, $node, ErrorConstants::TYPE_SECURITY_BACKTICK, "Unsafe operator (back tick)");
		}
	}
}