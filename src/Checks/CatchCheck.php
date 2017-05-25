<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\Checks;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use BambooHR\Guardrail\Scope;

class CatchCheck extends BaseCheck
{
	function getCheckNodeTypes() {
		return [\PhpParser\Node\Stmt\Catch_::class];
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
		$name = $node->type->toString();
		if ($this->symbolTable->ignoreType($name)) {
			return;
		}
		$this->incTests();
		if (!$this->symbolTable->isDefinedClass($name)) {
			$this->emitError($fileName,$node,ErrorConstants::TYPE_UNKNOWN_CLASS, "Attempt to catch unknown type: $name");
		}
	}
}