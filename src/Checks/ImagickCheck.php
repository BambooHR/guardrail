<?php

namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Scope;
use PhpParser\Node;

class ImagickCheck extends BaseCheck
{
	/**
	 * @return string[]
	 */
	function getCheckNodeTypes() {
		return [ Node\Expr\New_::class ];
	}

	function run($fileName, Node $node, ?Node\Stmt\ClassLike $inside = null, ?Scope $scope = null) {
		if ($node instanceof Node\Expr\New_) {
			if ($node->class instanceof Node\Name && strcasecmp($node->class->toString(), "imagick") == 0) {
				$this->emitError($fileName, $node, ErrorConstants::TYPE_UNSAFE_IMAGICK, "Attempt to Instantiate unsafe class: " . $node->class->toString());
			}
		}
	}
}