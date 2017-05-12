<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */
namespace BambooHR\Guardrail\Abstractions;

use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\Interface_;
use BambooHR\Guardrail\NodeVisitors\Grabber;
use BambooHR\Guardrail\Abstractions\ClassInterface;
use BambooHR\Guardrail\Abstractions\ClassMethod;
use PhpParser\Node\Stmt\PropertyProperty;

class Class_ implements ClassInterface {
	private $class;

	function __construct(\PhpParser\Node\Stmt\ClassLike $class) {
		$this->class = $class;
	}
	function getName() {
		return strval($this->class->namespacedName);
	}

	function isDeclaredAbstract() {
		return ($this->class instanceof Class_ ? $this->class->isAbstract() : false);
	}

	function getMethodNames() {
		$ret = [];
		foreach($this->class->getMethods() as $method) {
			$ret[] = $method->name;
		}
		return $ret;
	}

	function getParentClassName() {
		return $this->class instanceof \PhpParser\Node\Stmt\Class_ ? strval($this->class->extends) : "";
	}

	function isInterface() {
		return $this->class instanceof \PhpParser\Node\Stmt\Interface_;
	}

	function getInterfaceNames() {
		$ret = [];
		if($this->class instanceof Interface_) {
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

	function getMethod($name) {
		$method = $this->class->getMethod($name);
		return $method ?  new ClassMethod($method) : null;
	}

	function hasConstant($name) {
		$constants = Grabber::filterByType($this->class->stmts, ClassConst::class);
		foreach($constants as $constList) {
			foreach($constList->consts as $const) {
				if (strcasecmp($const->name, $name) == 0) {
					return true;
				}
			}
		}
		return false;
	}

	function getPropertyNames() {
		$properties = Grabber::filterByType($this->class->stmts, \PhpParser\Node\Stmt\Property::class);
		foreach($properties as $prop) {
			/** @var \PhpParser\Node\Stmt\Property $prop */
			foreach($prop->props as $propertyProperty) {
				/** @var PropertyProperty $propertyProperty */
				$ret[] = $propertyProperty->name;
			}
		}
		return $ret;
	}

	function getProperty($name) {
		$properties = Grabber::filterByType($this->class->stmts, \PhpParser\Node\Stmt\Property::class);
		foreach($properties as $prop) {
			/** @var \PhpParser\Node\Stmt\Property $prop */
			foreach($prop->props as $propertyProperty) {
				/** @var PropertyProperty $propertyProperty */
				if($propertyProperty->name==$name) {
					if($prop->isPrivate()) {
						$access="private";
					} else if($prop->isProtected()) {
						$access="protected";
					} else {
						$access="public";
					}
					return new Property($propertyProperty->name, "", $prop->type , $prop->isStatic());
				}
			}
		}
	}
}