<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Checks\BaseCheck;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Trait_;
use BambooHR\Guardrail\NodeVisitors\Grabber;
use PhpParser\Node\Name;
use BambooHR\Guardrail\Scope;

class ClassConstantCheck extends BaseCheck {

	function getCheckNodeTypes() {
		return [\PhpParser\Node\Expr\ClassConstFetch::class];
	}

	/**
	 * @param ClassLike $class
	 * @param string    $constantName
	 * @return ClassConst
	 */
	function findConstant(\BambooHR\Guardrail\Abstractions\ClassInterface $class, $constantName) {
		if($class->hasConstant($constantName)) {
			return true;
		}

		if ($class->getParentClassName()) {
			$parentClass = $this->symbolTable->getAbstractedClass($class->getParentClassName());
			if ($parentClass && $this->findConstant($parentClass, $constantName)) {
				return true;
			}
		}

		foreach($class->getInterfaceNames() as $interfaceName) {
			$interface=$this->symbolTable->getAbstractedClass($interfaceName);
			if($interface && $this->findConstant($interface, $constantName)) {
				return true;
			}
		}

		return false;
	}

	function run($fileName, $node, ClassLike $inside=null, Scope $scope=null) {
		if ($node->class instanceof Name) {
			$name = $node->class->toString();
			$constantName = strval($node->name);

			if ($this->symbolTable->ignoreType($name)) {
				return;
			}

			switch(strtolower($name)) {
				case 'self':
				case 'static':
					if(!$inside) {
						$this->emitError($fileName, $node, self::TYPE_SCOPE_ERROR, "Can't access using self:: outside of a class");
						return;
					}
					$name = $inside->namespacedName;
					break;
				case 'parent':
					if(!$inside) {
						$this->emitError($fileName, $node, self::TYPE_SCOPE_ERROR, "Can't access using parent:: outside of a class");
						return;
					}
					if ($inside->extends) {
						$name = strval($inside->extends);
					} else {
						$this->emitError($fileName, $node, self::TYPE_SCOPE_ERROR, "Can't access using parent:: in a class with no parent");
						return;
					}
					break;
			}

			$this->incTests();
			$class = $this->symbolTable->getAbstractedClass($name);
			if (!$class) {
				$this->emitError($fileName,$node,self::TYPE_UNKNOWN_CLASS, "That's not a thing.  Can't find class/interface $name");
				return;
			}

			if(strcasecmp($constantName,"class")!=0 && !$this->findConstant($class, $constantName)) {
				$this->emitError($fileName, $node, self::TYPE_UNKNOWN_CLASS_CONSTANT, "Reference to unknown constant $name::$constantName");
			}
		}
	}
}