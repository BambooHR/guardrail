<?php

namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Checks\BaseCheck;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\TypeComparer;
use Countable;
use PhpParser\Node;
use PhpParser\Node\Expr\Empty_;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassLike;

class CountableEmptinessCheck extends BaseCheck {
	/**
	 * @return string[]
	 */
	function getCheckNodeTypes() {
		return [Empty_::class];
	}

	function run($fileName, Node $node, ?ClassLike $inside = null, ?Scope $scope = null) {
		$type = $node->expr->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);

		if (!($type instanceof Name)) {
			return;
		}

		if ($this->symbolTable->isParentClassOrInterface(Countable::class, $type)) {
			$this->emitError(
				$fileName,
				$node,
				ErrorConstants::TYPE_COUNTABLE_EMPTINESS_CHECK,
				"Attempt to call empty() on a countable, will yield unexpected result. Use `isEmpty()`, `isNotEmpty()` or `count()` instead."
			);
		}
	}
}
