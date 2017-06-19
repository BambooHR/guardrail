<?php

namespace BambooHR\Guardrail\Checks;


use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\Util;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;

class ClassConsistencyCheck extends BaseCheck {
	function getCheckNodeTypes() {
		return [ Node\Stmt\Class_::class ];
	}

	private function getPropertyIterator($stmts) {
		foreach ($stmts as $statement) {
			if ($statement instanceof Node\Stmt\Property) {
				foreach ($statement->props as $propProp) {
					yield $propProp;
				}
			}
		}
	}

	public function run($fileName, Node $node, ClassLike $inside = null, Scope $scope = null) {
		if ($node instanceof Node\Stmt\Class_ ) {
			$methods =  $node->getMethods();
			foreach ($methods as $method) {
				foreach ($methods as $method2) {
					if (strcasecmp($method2->name, $method->name)==0 && $method != $method2) {
						$this->emitError($fileName, $method2, ErrorConstants::TYPE_DUPLICATE_METHOD,  "Duplicate method ".$method->name."() detected");
					}
				}
			}

			foreach ($this->getPropertyIterator($node->stmts) as $prop1) {
				/** @var Node\Stmt\PropertyProperty $prop2 */
				foreach ($this->getPropertyIterator($node->stmts) as $prop2) {
					if($prop1->name == $prop2->name) {
						$this->emitError($fileName, $prop2, ErrorConstants::TYPE_DUPLICATE_PROPERTY, "Duplicate property ".$inside->name."->".$prop1->name. "detected");
					}
				}
				if ($inside instanceof Node\Stmt\Class_) {
					if ($prop1->getttribute("ImportedFromTrait") && $inside->extends) {
						$prop = Util::findAbstractedProperty($inside->extends, $prop1->name, $this->symbolTable);
						if($prop) {
							$this->emitError($fileName, $prop1, ErrorConstants::TYPE_DUPLICATE_PROPERTY, "Trait property conflicts with member variable from a parent class");
						}
					}
				}
			}
		}
	}
}