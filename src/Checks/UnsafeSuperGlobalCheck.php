<?php

namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Scope;
use PhpParser\Node;

class UnsafeSuperGlobalCheck extends BaseCheck {

	/**
	 * @return string[]
	 */
	function getCheckNodeTypes() {
		return [Node\Expr\Variable::class];
	}

	function run($fileName, Node $node, ?Node\Stmt\ClassLike $inside = null, ?Scope $scope = null) {
		if ($node instanceof Node\Expr\Variable && $this->isUnsafeSuperGlobal($node->name)) {
			$this->emitError(
				$fileName,
				$node,
				ErrorConstants::TYPE_UNSAFE_SUPERGLOBAL,
				"Attempt to use unsafe superglobal {$node->name} detected on line {$node->getLine()}"
			);
		}
	}

	/**
	 *
	 * @return bool
	 */
	function isUnsafeSuperGlobal($name) {
		return is_string($name) && $name === '_REQUEST';
	}
}
