<?php

namespace BambooHR\Guardrail\Abstractions;

use BambooHR\Guardrail\Util;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Name;

/**
 * Guardrail.  Copyright (c) 2016-2023, BambooHR.
 * Apache 2.0 License
 */

/**
 * Class ReflectedClassMethod
 *
 * @package BambooHR\Guardrail\Abstractions
 */
class ReflectedClassMethod implements MethodInterface {
	/**
	 * @var \ReflectionMethod
	 */
	private $refl;

	private ClassInterface $class;

	/**
	 * ReflectedClassMethod constructor.
	 *
	 * @param ClassInterface    $class The class this method belongs to
	 * @param \ReflectionMethod $refl  Instance of ReflectionMethod
	 */
	public function __construct(ClassInterface $class, \ReflectionMethod $refl) {
		$this->refl = $refl;
		$this->class = $class;
	}

	/**
	 * isStatic
	 *
	 * @return bool
	 */
	public function isStatic() {
		return $this->refl->isStatic();
	}

	function getClass(): ClassInterface {
		return $this->class;
	}

	/**
	 * isDeprecated
	 *
	 * @return bool
	 */
	public function isDeprecated() {
		return $this->refl->isDeprecated();
	}

	/**
	 * isInternal
	 *
	 * @return bool
	 */
	public function isInternal() {
		return true;
	}

	public function getComplexReturnType() {
		if ( method_exists($this->refl, "getReturnType")) {
			return Util::reflectionTypeToPhpParserType($this->refl->getReturnType());
		}
		return null;
	}

	/**
	 * getDocBlockReturnType
	 *
	 * @return null
	 */
	public function getDocBlockReturnType() {
		return null;
	}

	/**
	 * isAbstract
	 *
	 * @return bool
	 */
	public function isAbstract() {
		return $this->refl->isAbstract();
	}

	/**
	 * getAccessLevel
	 *
	 * @return string
	 */
	public function getAccessLevel() {
		if ($this->refl->isPrivate()) {
			return "private";
		}
		if ($this->refl->isProtected()) {
			return "protected";
		}
		return "public";
	}

	/**
	 * getMinimumRequiredParameters
	 *
	 * @return int
	 */
	public function getMinimumRequiredParameters() {

		$class = strtolower($this->refl->getDeclaringClass()->getName());
		$method = strtolower($this->refl->getName());
		if (
			($class == "phar"        && $method == "running") ||
			($class == "imagick"     && $method == "__construct") ||
			($class == "domdocument" && $method == "__construct")
		) {
			return 0;
		} else {
			return $this->refl->getNumberOfRequiredParameters();
		}
	}

	/**
	 * getParameters
	 *
	 * @return array
	 */
	public function getParameters() {
		$ret = [];
		$params = $this->refl->getParameters();
		/** @var \ReflectionParameter $param */
		foreach ($params as $param) {
			$type = Util::reflectionTypeToPhpParserType($param->getType());
			$ret[] = new FunctionLikeParameter($type, $param->name, $param->isOptional(), $param->isPassedByReference(), method_exists($param, "allowsNull") ? $param->allowsNull() : false);
		}
		return $ret;
	}

	/**
	 * @guardrail-ignore Standard.Unknown.Class.Method
	 * @return bool
	 */
	public function hasNullableReturnType() {
		if ( method_exists($this->refl, "getReturnType")) {
			$type = $this->refl->getReturnType();
			if ($type) {
				return $type->allowsNull();
			}
		}
		return false;
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
	 * getStartingLine
	 *
	 * @return int
	 */
	public function getStartingLine() {
		return 0;
	}

	/**
	 * isVariadic
	 *
	 * @return bool
	 * @guardrail-ignore Standard.Unknown.Class.Method
	 */
	public function isVariadic() {
		if (method_exists($this->refl, "isVariadic")) {
			return $this->refl->isVariadic();
		} else {
			return true; // We assume internal functions are variadic so that we don't get bombarded with warnings.
		}
	}

	public function getAttributes(string $name): array {
		$attributes = $this->refl->getAttributes($name);
		return array_map(function ($attr) {
			return new Attribute(new Name($attr->getName()));
		}, $attributes);
	}

	function getThrowsList(): array {
		return [];
	}
}