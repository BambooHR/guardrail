<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\Checks;

use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassLike;
use BambooHR\Guardrail\Scope;

class InstanceOfCheck extends BaseCheck
{
	function getCheckNodeTypes() {
		return [\PhpParser\Node\Expr\Instanceof_::class];
	}

	function run($fileName, $node, ClassLike $inside=null, Scope $scope=null) {
		if ($node->class instanceof Name) {
			$name = $node->class->toString();
			if (strcasecmp($name, "self") != 0 && strcasecmp($name, "static") != 0 && !$this->symbolTable->ignoreType($name)) {
				$this->incTests();
				if (!$this->symbolTable->isDefinedClass($name)) {
					$this->emitError($fileName,$node,self::TYPE_UNKNOWN_CLASS, "Instance of references unknown class $name");
				}
			}
		}
	}
}
