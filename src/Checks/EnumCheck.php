<?php

namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Abstractions\ClassMethod;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\TypeComparer;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Enum_;

class EnumCheck extends BaseCheck {

	function getCheckNodeTypes() {
		return [Enum_::class];
	}

	function run($fileName, Node $node, ClassLike $inside = null, Scope $scope = null) {
		if ($node instanceof Enum_) {
			$isBacked = !is_null($node->scalarType);
			foreach($node->stmts as $stmt) {
				if ($stmt instanceof Node\Stmt\EnumCase) {
					if ($isBacked) {
						if (!$stmt->expr) {
							$this->emitError($fileName, $node, ErrorConstants::TYPE_ILLEGAL_ENUM, "Backed enum case needs a value");
						} else {
							$caseInferredType = $stmt->expr->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);
							if (strval($node->scalarType) != strval($caseInferredType)) {
								$this->emitError($fileName, $node, ErrorConstants::TYPE_ILLEGAL_ENUM, "Enums values must be compatible with declared backing type");
							}
						}
					} elseif ($stmt->expr!=null) {
						$this->emitError($fileName, $node, ErrorConstants::TYPE_ILLEGAL_ENUM, "Attempt to use a backing value in a unbacked enum type");
					}
				}
				if ($stmt instanceof Node\Stmt\Property) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_ILLEGAL_ENUM, "Enums may not contain properties");
				}
				if ($stmt instanceof ClassMethod && $stmt->getName()=="cases") {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_ILLEGAL_ENUM, "Enums may not implements the \"cases()\" method");
				}
			}
		}
	}
}