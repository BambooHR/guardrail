<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\Checks;

use PhpParser\Node;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\ClassLike;
use BambooHR\Guardrail\Scope;

/**
 * Class CatchCheck
 *
 * @package BambooHR\Guardrail\Checks
 */
class CatchCheck extends BaseCheck {

	/**
	 * getCheckNodeTypes
	 *
	 * @return string[]
	 */
	public function getCheckNodeTypes() {
		return [Catch_::class];
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
	public function run($fileName, Node $node, ClassLike $inside = null, Scope $scope = null) {
		$name = $node->type->toString();
		if ($this->symbolTable->ignoreType($name)) {
			// exception is in the ignore list... but if the error constant is turned on, we should emit this error
			if ('exception' == $node->var) {
				$this->emitError($fileName, $node, ErrorConstants::TYPE_EXCEPTION_BASE, "Catching the base Exception class may be too broad");
			}
			return;
		}

		if (!$this->symbolTable->isDefinedClass($name)) {
			$this->emitError($fileName, $node, ErrorConstants::TYPE_UNKNOWN_CLASS, "Attempt to catch unknown type: $name");
		}
	}
}