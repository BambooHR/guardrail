<?php namespace BambooHR\Guardrail\Abstractions;

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
		return $this->refl->isStatic();
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

	/**
	 * getReturnType
	 *
	 * @return string
	 */
	public function getReturnType() {
		return "";
	}

	/**
	 * isAbstract
	 *
	 * @return mixed
	 */
	public function isAbstract() {
		return $this->refl->isAbstract();
	}

	/**
	 * getDocBlockReturnType
	 *
	 * @return string
	 */
	public function getDocBlockReturnType() {
		return "";
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
			$type = $param->getClass() ? $param->getClass()->name : '';
			$isPassedByReference = $param->isPassedByReference();
			if ($this->getName() == "preg_match" && $index == 2) {
				$isPassedByReference = true;
			}
			$ret[] = new FunctionLikeParameter( $type, $param->name, $param->isOptional(), $isPassedByReference);
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
	 */
	public function isVariadic() {
		if (method_exists($this->refl, "isVariadic")) {
			return $this->refl->isVariadic();
		} else {
			return true; // We assume internal functions are variadic so that we don't get bombarded with warnings.
		}
	}
}