<?php namespace BambooHR\Guardrail\Abstractions;

use BambooHR\Guardrail\Util;

/**
 * Guardrail.  Copyright (c) 2016-2023, BambooHR.
 * Apache 2.0 License
 */

/**
 * Class ReflectedClass
 *
 * @package BambooHR\Guardrail\Abstractions
 */
class ReflectedClass implements ClassInterface {

	/**
	 * @var \ReflectionClass
	 */
	private $refl;

	/**
	 * ReflectedClass constructor.
	 *
	 * @param \ReflectionClass $refl Instance of ReflectionClass
	 */
	public function __construct(\ReflectionClass $refl) {
		$this->refl = $refl;
	}

	/**
	 * getParentClassName
	 *
	 * @return string
	 */
	public function getParentClassName() {
		$parent = $this->refl->getParentClass();
		return $parent ? $parent->getName() : "";
	}

	/**
	 * getInterfaceNames
	 *
	 * @return array
	 */
	public function getInterfaceNames() {
		$names = $this->refl->getInterfaceNames();
		if (strcasecmp($this->refl->name, 'exception') == 0) {
			$names[] = 'Throwable';
		}
		return $names;
	}

	/**
	 * isInterface
	 *
	 * @return bool
	 */
	public function isInterface() {
		return $this->refl->isInterface();
	}

	/**
	 * isDeclaredAbstract
	 *
	 * @return bool
	 */
	public function isDeclaredAbstract() {
		return $this->refl->isAbstract();
	}

	/**
	 * getMethodNames
	 *
	 * @return array
	 */
	public function getMethodNames() {
		$ret = [];
		foreach ($this->refl->getMethods() as $method) {
			$ret[] = $method->name;
		}
		return $ret;
	}

	/**
	 * hasConstant
	 *
	 * @param string $name The name
	 *
	 * @return bool
	 */
	public function hasConstant($name) {
		$constants = $this->refl->getConstants();
		return array_key_exists($name, $constants);
	}

	/**
	 * getMethod
	 *
	 * @param string $name The name of the method to get
	 *
	 * @return \BambooHR\Guardrail\Abstractions\ReflectedClassMethod|null
	 */
	public function getMethod($name) {
		try {
			$method = $this->refl->getMethod($name);
			if ($method) {
				return new ReflectedClassMethod($method);
			}
		} catch (\ReflectionException $exception) {
			return null;
		}
		return null;
	}

	/**
	 * getName
	 *
	 * @return string
	 */
	public function getName() {
		return $this->refl->getName();
	}

	/**
	 * getProperty
	 *
	 * @param string $name The name of the property
	 *
	 * @return Property|null
	 */
	public function getProperty($name) {
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
				return new Property($prop->getName(), "", $access, $modifiers & \ReflectionProperty::IS_STATIC );
			}
			return null;
		} catch (\ReflectionException $exception) {
			return null;
		}
	}

	/**
	 * getPropertyNames
	 *
	 * @return array
	 */
	public function getPropertyNames() {
		$ret = [];
		$props = $this->refl->getProperties();
		foreach ($props as $prop) {
			$ret[] = $prop->getName();
		}
		return $ret;
	}
}