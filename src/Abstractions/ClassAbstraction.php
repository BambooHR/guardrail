<?php namespace BambooHR\Guardrail\Abstractions;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Config;
use PhpParser\Node\Stmt\Class_;
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
	public function getName():string {
		$class = $this->class;
		return isset($class->namespacedName) ? strval($class->namespacedName) : "";
	}

	/**
	 * isDeclaredAbstract
	 *
	 * @return bool
	 */
	public function isDeclaredAbstract():bool {
		$class = $this->class;
		if ($class instanceof Class_) {
			return $class->isAbstract();
		} else {
			return false;
		}
	}

	/**
	 * getMethodNames
	 *
	 * @return array
	 */
	public function getMethodNames():array {
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
	public function getParentClassName():string {
		$class = $this->class;
		if ($class instanceof \PhpParser\Node\Stmt\Class_) {
			return strval($class->extends);
		} else {
			return "";
		}
	}

	/**
	 * isInterface
	 *
	 * @return bool
	 */
	public function isInterface():bool {
		return $this->class instanceof \PhpParser\Node\Stmt\Interface_;
	}

	/**
	 * getInterfaceNames
	 *
	 * @return array
	 */
	public function getInterfaceNames():array {
		$ret = [];
		$class = $this->class;
		if ($class instanceof Interface_) {
			foreach ($class->extends as $extend) {
				$ret[] = strval($extend);
			}
		} else {
			/** @var Class_ $class */
			foreach ($class->implements as $implement) {
				$ret[] = strval($implement);
			}
		}
		return $ret;
	}

	/**
	 * getMethod
	 *
	 * @param string $name Instance of ClassMethod
	 *
	 * @return MethodInterface|null
	 */
	public function getMethod(string $name):?MethodInterface {
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
	public function hasConstant(string $name):bool {
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
	public function getPropertyNames():array {
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
	public function getProperty(string $name):?Property {
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
					$type = strval($prop->type);
					if (Config::shouldUseDocBlockForProperties() && empty($type)) {
						$type = $propertyProperty->getAttribute("namespacedType");
					}
					return new Property($propertyProperty->name->name, $type, $access, $prop->isStatic());
				}
			}
		}
		return null;
	}
}