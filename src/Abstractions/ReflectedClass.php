<?php 

namespace BambooHR\Guardrail\Abstractions;

use BambooHR\Guardrail\TypeComparer;
use BambooHR\Guardrail\Util;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\DNumber;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;

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

	public function getConstantExpr($name): null|Name|Identifier {
		if ($this->refl->hasConstant($name)) {
			$constant = $this->refl->getConstant($name);
			if (is_int($constant)) {
				return TypeComparer::identifierFromName("int");
			} elseif (is_bool($constant)) {
				return TypeComparer::identifierFromName("bool");
			} elseif (is_string($constant)) {
				return TypeComparer::identifierFromName("string");
			} else {
				return TypeComparer::identifierFromName("mixed");
			}
		}
		return null;
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
				return new ReflectedClassMethod($this, $method);
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
				} elseif ($modifiers & \ReflectionProperty::IS_PROTECTED) {
					$access = "protected";
				} else {
					$access = "public";
				}
				$type = Util::reflectionTypeToPhpParserType($prop->getType());
				return new Property($this, $prop->getName(), $type, $access, $modifiers & \ReflectionProperty::IS_STATIC, $modifiers & \ReflectionProperty::IS_READONLY );
			}
			return null;
		} catch (\ReflectionException) {
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

	public function isEnum(): bool {
		return $this->refl instanceof \ReflectionEnum;
	}

	public function isReadOnly(): bool {
		if (method_exists($this->refl, "isReadOnly")) {
			return $this->refl->isReadOnly();
		}
		return false;
	}
}