<?php namespace BambooHR\Guardrail\Abstractions;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Interface_;
use BambooHR\Guardrail\NodeVisitors\Grabber;
use PhpParser\Node\Stmt\PropertyProperty;

/**
 * Class ClassAbstraction
 *
 * @package BambooHR\Guardrail\Abstractions
 */
class ClassAbstraction implements ClassInterface {

	/**
	 * @var ClassLike
	 */
	private $class;

	/**
	 * ClassAbstraction constructor.
	 *
	 * @param ClassLike $class Instance of ClassLike
	 */
	public function __construct(ClassLike $class) {
		$this->class = $class;
	}

	/**
	 * getName
	 *
	 * @return string
	 */
	public function getName() {
		return strval($this->class->namespacedName);
	}

	/**
	 * isDeclaredAbstract
	 *
	 * @return bool
	 */
	public function isDeclaredAbstract() {
		return ($this->class instanceof ClassAbstraction ? $this->class->isAbstract() : false);
	}

	/**
	 * getMethodNames
	 *
	 * @return array
	 */
	public function getMethodNames() {
		$ret = [];
		foreach ($this->class->getMethods() as $method) {
			$ret[] = $method->name;
		}
		return $ret;
	}

	/**
	 * getParentClassName
	 *
	 * @return string
	 */
	public function getParentClassName() {
		return $this->class instanceof \PhpParser\Node\Stmt\Class_ ? strval($this->class->extends) : "";
	}

	/**
	 * isInterface
	 *
	 * @return bool
	 */
	public function isInterface() {
		return $this->class instanceof \PhpParser\Node\Stmt\Interface_;
	}

	/**
	 * getInterfaceNames
	 *
	 * @return array
	 */
	public function getInterfaceNames() {
		$ret = [];
		if ($this->class instanceof Interface_) {
			foreach ($this->class->extends as $extend) {
				$ret[] = strval($extend);
			}
		} else {
			foreach ($this->class->implements as $implement) {
				$ret[] = strval($implement);
			}
		}
		return $ret;
	}

	/**
	 * getMethod
	 *
	 * @param ClassMethod $name Instance of ClassMethod
	 *
	 * @return ClassMethod|null
	 */
	public function getMethod($name) {
		$method = $this->class->getMethod($name);
		return $method ? new ClassMethod($method) : null;
	}

	/**
	 * hasConstant
	 *
	 * @param string $name Property name
	 *
	 * @return bool
	 */
	public function hasConstant($name) {
		$constants = Grabber::filterByType($this->class->stmts, ClassConst::class);
		foreach ($constants as $constList) {
			foreach ($constList->consts as $const) {
				if (strcasecmp($const->name, $name) == 0) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * getPropertyNames
	 *
	 * @return array
	 */
	public function getPropertyNames() {
		$ret = [];
		$properties = Grabber::filterByType($this->class->stmts, \PhpParser\Node\Stmt\Property::class);
		foreach ($properties as $prop) {
			/** @var \PhpParser\Node\Stmt\Property $prop */
			foreach ($prop->props as $propertyProperty) {
				/** @var PropertyProperty $propertyProperty */
				$ret[] = $propertyProperty->name;
			}
		}
		return $ret;
	}

	/**
	 * getProperty
	 *
	 * @param string $name The name of the property
	 *
	 * @return Property
	 */
	public function getProperty($name) {
		$properties = Grabber::filterByType($this->class->stmts, \PhpParser\Node\Stmt\Property::class);
		foreach ($properties as $prop) {
			/** @var \PhpParser\Node\Stmt\Property $prop */
			foreach ($prop->props as $propertyProperty) {
				/** @var PropertyProperty $propertyProperty */
				if ($propertyProperty->name == $name) {
					if ($prop->isPrivate()) {
						$access = "private";
					} else if ($prop->isProtected()) {
						$access = "protected";
					} else {
						$access = "public";
					}
					return new Property($propertyProperty->name, "", $access, $prop->isStatic());
				}
			}
		}
	}
}