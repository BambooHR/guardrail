<?php

namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Scope;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\PropertyProperty;

class ReadOnlyPropertyCheck extends BaseCheck {
	function getCheckNodeTypes() {
		return [PropertyProperty::class];
	}

	public function run($fileName, Node $node, ?ClassLike $inside = null, ?Scope $scope = null) {
		if ($node instanceof PropertyProperty) {
			$parentNodes = $scope?->getParentNodes();

			/** @var Node\Stmt\Property $prop */
			$prop = end($parentNodes);
			$isReadOnlyClass = $inside instanceof Node\Stmt\Class_ && $inside->isReadonly();
			if ($prop->isReadonly() || $isReadOnlyClass) {
				if ($node->default !== null) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_READONLY_DECLARATION, "Readonly properties can't have a default value");
				}
				if ($prop->type === null) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_READONLY_DECLARATION, "Readonly properties must have a declared type");
				}
			}
		}
	}
}
