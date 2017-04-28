<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Checks\BaseCheck;
use PhpParser\Node\Stmt\ClassLike;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\Util;

class CatchCheck extends BaseCheck
{
	function getCheckNodeTypes() {
		return [\PhpParser\Node\Stmt\Catch_::class];
	}

	/**
	 * @param string $fileName
	 * @param \PhpParser\Node\Stmt\Catch_ $node
	 */
	function run($fileName, $node, ClassLike $inside=null, Scope $scope=null) {

		$name = $node->type->toString();
		if ($this->symbolTable->ignoreType($name)) {
			return;
		}
		$this->incTests();
		if (!$this->symbolTable->getAbstractedClass($name)) {
			$this->emitError($fileName,$node,self::TYPE_UNKNOWN_CLASS, "Attempt to catch unknown type: $name");
		}
	}
}