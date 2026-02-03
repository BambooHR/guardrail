<?php

namespace BambooHR\Guardrail\Checks;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Abstractions\ClassInterface;
use BambooHR\Guardrail\Scope;
use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Interface_;

/**
 * Class ClassConstantCheck
 *
 * @package BambooHR\Guardrail\Checks
 */
class ClassConstantCheck extends BaseCheck {
	/**
	 * getCheckNodeTypes
	 *
	 * @return array
	 */
	public function getCheckNodeTypes() {
		return [ClassConstFetch::class];
	}

	/**
	 * findConstant
	 *
	 * @param ClassInterface $class        Instance of ClassInterface
	 * @param string         $constantName The name of the constant
	 *
	 * @return bool
	 */
	public function findConstant(ClassInterface $class, $constantName) {
		if ($class->hasConstant($constantName)) {
			return true;
		}

		if ($class->getParentClassName()) {
			$parentClass = $this->symbolTable->getAbstractedClass($class->getParentClassName());
			if ($parentClass && $this->findConstant($parentClass, $constantName)) {
				return true;
			}
		}

		foreach ($class->getInterfaceNames() as $interfaceName) {
			$interface = $this->symbolTable->getAbstractedClass($interfaceName);
			if ($interface && $this->findConstant($interface, $constantName)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * run
	 *
	 * @param string         $fileName The name of the file we are parsing
	 * @param Node           $node     Instance of the Node
	 * @param ClassLike|null $inside   Instance of the ClassLike (the class we are parsing) [optional]
	 * @param Scope|null     $scope    Instance of the Scope (all variables in the current state) [optional]
	 * @guardrail-ignore Standard.Unknown.Property
	 *
	 * @return void
	 */
	public function run($fileName, Node $node, ?ClassLike $inside = null, ?Scope $scope = null) {
		if ($node instanceof ClassConstFetch) {
			if ($node->class instanceof Name) {
				$name = $node->class->toString();
				$constantName = strval($node->name);

				if ($this->symbolTable->ignoreType($name)) {
					return;
				}

				switch (strtolower($name)) {
					case 'self':
					case 'static':
						if (!$inside) {
							$this->emitError($fileName, $node, ErrorConstants::TYPE_SCOPE_ERROR, "Can't access using self:: outside of a class");
							return;
						}
						$name = $inside->namespacedName;
						break;
					case 'parent':
						if (!$inside) {
							$this->emitError($fileName, $node, ErrorConstants::TYPE_SCOPE_ERROR, "Can't access using parent:: outside of a class");
							return;
						}
						if ($inside instanceof Class_) {
							$name = strval($inside->extends);
						} else if ($inside instanceof Interface_) {
							$name = strval($inside->extends);
						} else {
							$name = "";
						}
						if (empty($name)) {
							$this->emitError($fileName, $node, ErrorConstants::TYPE_SCOPE_ERROR, "Can't access using parent:: in a class with no parent");
							return;
						}
						break;
				}

				$class = $this->symbolTable->getAbstractedClass($name);
				if (!$class) {
					$class = $this->symbolTable->getAbstractedTrait($name);
				}
				if (!$class) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_UNKNOWN_CLASS, "That's not a thing.  Can't find class/interface/trait $name");
					return;
				}

				if (strcasecmp($constantName, "class") != 0 && !$this->findConstant($class, $constantName)) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_UNKNOWN_CLASS_CONSTANT, "Reference to unknown constant $name::$constantName");
				}
			}
		}
	}
}
