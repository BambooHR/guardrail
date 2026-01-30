<?php

namespace BambooHR\Guardrail\Abstractions;

use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\TypeComparer;
use BambooHR\Guardrail\Util;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */


/**
 * Class ReflectedFunction
 *
 * @package BambooHR\Guardrail\Abstractions
 */
class ReflectedFunction implements FunctionLikeInterface {
	/**
	 * @var \ReflectionFunction
	 */
	private $refl;

	/**
	 * ReflectedFunction constructor.
	 *
	 * @param \ReflectionFunction $refl Instance of ReflectionFunction
	 */
	public function __construct(\ReflectionFunction $refl) {
		$this->refl = $refl;
	}

	/**
	 * isStatic
	 *
	 * @return mixed
	 */
	public function isStatic() {
		return false;
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
		if (method_exists($this->refl, "getReturnType")) {
			$type = $this->refl->getReturnType();
			return Util::reflectionTypeToPhpParserType($type);
		}
		return null;
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
	 * isAbstract
	 *
	 * @return mixed
	 */
	public function isAbstract() {
		return false;
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
	 * getAccessLevel
	 *
	 * @return string
	 */
	public function getAccessLevel() {
		return "public";
	}

	/**
	 * getMinimumRequiredParameters
	 *
	 * @return int|mixed
	 */
	public function getMinimumRequiredParameters() {
		$min = self::getOverriddenMinimumParams($this->refl->name);
		return $min >= 0 ? $min : $this->refl->getNumberOfRequiredParameters();
	}

	/**
	 * getOverriddenMinimumParams
	 *
	 * @param string $name The name
	 *
	 * @return int|mixed
	 */
	private static function getOverriddenMinimumParams($name) {
		static $overrides = [
			"define" => 2,
			"implode" => 1,
			"strtok" => 1,
			"sprintf" => 1,
			"array_merge" => 1,
			"stream_set_timeout" => 2
		];
		$name = strtolower($name);

		return isset($overrides[$name]) ? $overrides[$name] : -1;
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
		foreach ($params as $index => $param) {
			$type = Util::reflectionTypeToPhpParserType($param->getType());
			$isPassedByReference = $param->isPassedByReference();
			$isNullable = (method_exists($param, "allowsNull") ? $param->allowsNull() : false);
			$name = $this->getName();
			switch ($index) {
				case 0:
					if (
						$name == "call_user_func" || $name == "call_user_func_array" ||
						$name == "forward_static_call" || $name == "forward_static_call_array"
					) {
						$type = TypeComparer::identifierFromName("callable");
					}
					break;
				case 1:

					if ($name == "usort" || $name == "uksort" || $name == "uasort") {
						$type = TypeComparer::identifierFromName("callable");
					}
					if ($name == 'exec') {
						$isPassedByReference = true;
					}
					break;
				case 2:
					if ($name == "preg_match" || $name == 'exec') {
						$isPassedByReference = true;
					}
					break;
			}
			$ret[] = new FunctionLikeParameter($type, $param->name, $param->isOptional(), $isPassedByReference, $isNullable);
		}
		return $ret;
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

	public function getThrowsList(): array {
		return [];
	}
}
