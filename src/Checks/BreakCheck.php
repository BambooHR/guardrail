<?php

namespace BambooHR\Guardrail\Checks;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Scope;
use PhpParser\Node;
use PhpParser\Node\Stmt\Break_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Continue_;

/**
 * Class BreakCheck
 *
 * @package BambooHR\Guardrail\Checks
 */
class BreakCheck extends BaseCheck {
	/**
	 * getCheckNodeTypes
	 *
	 * @return string[]
	 */
	public function getCheckNodeTypes() {
		return [ Break_::class, Continue_::class ];
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
		if ($node instanceof Break_) {
			if ($node->num != null) {
				$this->emitError($fileName, $node, ErrorConstants::TYPE_BREAK_NUMBER, "Usage of unsafe \"break [expression]\" form");
			}
		} else if ($node instanceof Continue_) {
			if ($node->num != null) {
				$this->emitError($fileName, $node, ErrorConstants::TYPE_BREAK_NUMBER, "Usage of unsafe \"continue [expression]\" form");
			}
		}
	}
}
