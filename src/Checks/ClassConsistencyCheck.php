<?php

namespace BambooHR\Guardrail\Checks;


use BambooHR\Guardrail\Abstractions\ClassAbstraction;
use BambooHR\Guardrail\Abstractions\Property;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\Util;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;

class ClassConsistencyCheck extends BaseCheck {
	/**
	 * @return array
	 */
	function getCheckNodeTypes() {
		return [ Node\Stmt\Class_::class ];
	}

	/**
	 * @param Node\Stmt[] $stmts The statements inside of a class
	 * @return Node\Stmt\PropertyProperty[]
	 */
	private function getProperties(array $stmts) {
		$ret = [];
		foreach ($stmts as $statement) {
			if ($statement instanceof Node\Stmt\Property) {
				foreach ($statement->props as $propProp) {
					$ret [] = $propProp;
				}
			}
		}
		return $ret;
	}

	/**
	 * @param string         $fileName -
	 * @param Node           $node     -
	 * @param ClassLike|null $inside   -
	 * @param Scope|null     $scope    -
	 * @return void
	 */
	public function run($fileName, Node $node, ClassLike $inside = null, Scope $scope = null) {

		if ($node instanceof Node\Stmt\Class_ ) {
			$methods = $node->getMethods();
			foreach($methods as $index1=>$method) {
				foreach($methods as $index2=>$method2) {
					if ($index1 < $index2 && strcasecmp($method2->name, $method->name) == 0) {
						$this->emitError($fileName, $method2, ErrorConstants::TYPE_DUPLICATE_METHOD, "Duplicate method " . $method->name . "() detected");
					}
				}
			}

			$list = $this->getProperties($node->stmts);
			foreach ($list as $index1=>$prop1) {
				foreach($list as $index2=>$prop2) {
					if ($prop1->name == $prop2->name && $index1 < $index2) {
						$this->emitError($fileName, $prop2, ErrorConstants::TYPE_DUPLICATE_PROPERTY, "Duplicate property " . $inside->name . "->" . $prop1->name . "detected");
					}
				}
				if ($inside instanceof Node\Stmt\Class_) {
					if ($inside->extends) {
						$wasFromTrait = $prop1->hasAttribute('ImportedFromTrait');
						$prop2 = Util::findAbstractedProperty($inside->extends, $prop1->name, $this->symbolTable);
						if ($prop2 &&  $wasFromTrait != $prop2->wasImportedFromTrait()) {
							$this->emitError($fileName, $prop1, ErrorConstants::TYPE_DUPLICATE_PROPERTY, "Trait property conflicts with member variable from a parent class");
						}
					}
				}
			}
		}
	}
}