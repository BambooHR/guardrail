<?php namespace BambooHR\Guardrail\Abstractions;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
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
	public function getParentClassName():string {
		$parent = $this->refl->getParentClass();
		return $parent ? $parent->getName() : "";
	}

	/**
	 * getInterfaceNames
	 *
	 * @return array
	 */
	public function getInterfaceNames():array {
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
	public function isInterface():bool {
		return $this->refl->isInterface();
	}

	/**
	 * isDeclaredAbstract
	 *
	 * @return bool
	 */
	public function isDeclaredAbstract():bool {
		return $this->refl->isAbstract();
	}

	/**
	 * getMethodNames
	 *
	 * @return array
	 */
	public function getMethodNames():array {
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
	public function hasConstant(string $name):bool {
		$constants = $this->refl->getConstants();
		return array_key_exists($name, $constants);
	}

	/**
	 * getMethod
	 *
	 * @param string $name The name of the method to get
	 *
	 * @return MethodInterface|null
	 */
	public function getMethod(string $name):?MethodInterface {
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
	public function getName():string {
		return $this->refl->getName();
	}

	/**
	 * getProperty
	 *
	 * @param string $name The name of the property
	 *
	 * @return Property|null
	 */
	public function getProperty(string $name):?Property {
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
		} catch (\ReflectionException $exception) {
			return null;
		}
	}

	/**
	 * getPropertyNames
	 *
	 * @return array
	 */
	public function getPropertyNames():array {
		$ret = [];
		$props = $this->refl->getProperties();
		foreach ($props as $prop) {
			$ret[] = $prop->getName();
		}
		return $ret;
	}
}