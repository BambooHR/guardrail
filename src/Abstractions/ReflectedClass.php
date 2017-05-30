<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\Abstractions;

use BambooHR\Guardrail\Abstractions\ClassInterface;
use BambooHR\Guardrail\Abstractions\ReflectedClassMethod;

class ReflectedClass implements ClassInterface {
	/**
	 * @var \ReflectionClass
	 */
	private $refl;

	function __construct(\ReflectionClass $refl) {
		$this->refl = $refl;
	}

	function getParentClassName() {
		$parent = $this->refl->getParentClass();
		return $parent ? $parent->getName() : "";
	}

	function getInterfaceNames() {
		return $this->refl->getInterfaceNames();
	}

	function isInterface() {
		return $this->refl->isInterface();
	}

	function isDeclaredAbstract() {
		return $this->refl->isAbstract();
	}

	function getMethodNames() {
		$ret = [];
		foreach ($this->refl->getMethods() as $method) {
			$ret[] = $method->name;
		}
		return $ret;
	}

	function hasConstant($name) {
		$constants = $this->refl->getConstants();
		return array_key_exists($name, $constants);
	}

	function getMethod($name) {
		try {
			$method = $this->refl->getMethod($name);
			if ($method) return new ReflectedClassMethod($method);
		} catch (\ReflectionException $e) {
			return null;
		}
		return null;
	}

	function getName() {
		return $this->refl->getName();
	}

	function getProperty($name) {
		try {
			$prop = $this->refl->getProperty($name);
			if ($prop) {
				$modifiers = $prop->getModifiers();

				if ($modifiers & \ReflectionProperty::IS_PRIVATE) {
					$access = "private";
				} else if ($modifiers & \ReflectionProperty::IS_PROTECTED) {
					$access = "protected";
				} else {
					$access = "public";
				}
				return new Property($prop->getName(), $access, "", $modifiers & \ReflectionProperty::IS_STATIC );
			}
			return null;
		} catch (\ReflectionException $e) {
			return null;
		}
	}

	function getPropertyNames() {
		$ret = [];
		$props = $this->refl->getProperties();
		foreach ($props as $prop) {
			$ret[] = $prop->getName();
		}
		return $ret;
	}
}