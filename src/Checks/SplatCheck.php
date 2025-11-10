<?php

namespace BambooHR\Guardrail\Checks;


use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\TypeComparer;
use MongoDB\BSON\Type;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrayItem;

class SplatCheck extends BaseCheck {

	/**
	 * getCheckNodeTypes
	 *
	 * @return array
	 */
	public function getCheckNodeTypes() {
		return [ ArrayItem::class ];
	}

	/**
	 * run
	 *
	 * @param string                   $fileName The name of the file we are parsing
	 * @param Node                     $node     Instance of the Node
	 * @param Node\Stmt\ClassLike|null $inside   Instance of the ClassLike (the class we are parsing) [optional]
	 * @param Scope|null               $scope    Instance of the Scope (all variables in the current state) [optional]
	 *
	 * @return void
	 */
	public function run($fileName, Node $node, ?Node\Stmt\ClassLike $inside=null, ?Scope $scope = null) {
		if ($node instanceof ArrayItem) {
			if ($node->unpack) {
				$type = $node->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);
				$tc=new TypeComparer($this->symbolTable);
				if (!$tc->isTraversable($type)) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_SPLAT_MISMATCH, "Can't use ... here.  Value is not an array or traversable.");
				}
			}
		}
	}


}