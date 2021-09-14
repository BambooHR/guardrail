<?php namespace BambooHR\Guardrail\Abstractions;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
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

	/**
	 * ReflectedClassMethod constructor.
	 *
	 * @param \ReflectionMethod $refl Instance of ReflectionMethod
	 */
	public function __construct(\ReflectionMethod $refl) {
		$this->refl = $refl;
	}

	/**
	 * isStatic
	 *
	 * @return bool
	 */
	public function isStatic():bool {
		return $this->refl->isStatic();
	}

	/**
	 * isDeprecated
	 *
	 * @return bool
	 */
	public function isDeprecated():bool {
		return $this->refl->isDeprecated();
	}

	/**
	 * isInternal
	 *
	 * @return bool
	 */
	public function isInternal():bool {
		return true;
	}

	/**
	 * getReturnType
	 * @guardrail-ignore Standard.Unknown.Class.Method
	 * @return string
	 */
	public function getReturnType():string {
		if ( method_exists($this->refl, "getReturnType")) {
			$type = $this->refl->getReturnType();
			if ($type) {
				return $type->getName();
			}
		}
		return "";
	}

	/**
	 * getDocBlockReturnType
	 *
	 * @return string
	 */
	public function getDocBlockReturnType():string {
		return "";
	}

	/**
	 * isAbstract
	 *
	 * @return bool
	 */
	public function isAbstract():bool {
		return $this->refl->isAbstract();
	}

	/**
	 * getAccessLevel
	 *
	 * @return string
	 */
	public function getAccessLevel():string {
		if ($this->refl->isPrivate()) {
			return "private";
		}
		if ($this->refl->isPublic()) {
			return "public";
		}
		if ($this->refl->isProtected()) {
			return "protected";
		}
	}

	/**
	 * getMinimumRequiredParameters
	 *
	 * @return int
	 */
	public function getMinimumRequiredParameters():int {

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
	public function getParameters():array {
		$ret = [];
		$params = $this->refl->getParameters();
		/** @var \ReflectionParameter $param */
		foreach ($params as $param) {
			$type = $param->getClass() ? $param->getClass()->name : '';
			$ret[] = new FunctionLikeParameter( $type, $param->name, $param->isOptional(), $param->isPassedByReference(), method_exists($param, "allowsNull") ? $param->allowsNull() : false);
		}
		return $ret;
	}

	/**
	 * @guardrail-ignore Standard.Unknown.Class.Method
	 * @return bool
	 */
	public function hasNullableReturnType():bool {
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
	public function getName():string {
		return $this->refl->getName();
	}

	/**
	 * getStartingLine
	 *
	 * @return int
	 */
	public function getStartingLine():int {
		return 0;
	}

	/**
	 * isVariadic
	 *
	 * @return bool
	 * @guardrail-ignore Standard.Unknown.Class.Method
	 */
	public function isVariadic():bool {
		if (method_exists($this->refl, "isVariadic")) {
			return $this->refl->isVariadic();
		} else {
			return true; // We assume internal functions are variadic so that we don't get bombarded with warnings.
		}
	}
}