<?php namespace BambooHR\Guardrail\Abstractions;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

/**
 * Class Property
 *
 * @package BambooHR\Guardrail\Abstractions
 */
class Property {

	/**
	 * @var string
	 */
	private $type;

	/**
	 * @var string
	 */
	private $name;

	/**
	 * @var string
	 */
	private $access;

	/**
	 * @var bool
	 */
	private $static;

	/**
	 * @var string
	 */
	private $fromTrait;

	/**
	 * Property constructor.
	 *
	 * @param string $name      The name of the property
	 * @param string $type      The type of the property
	 * @param string $access    The access
	 * @param bool   $isStatic  Is it static
	 * @param string $fromTrait The name of the trait that this property was imported from.
	 */
	public function __construct($name,$type, $access, $isStatic, $fromTrait="") {
		$this->name = $name;
		$this->access = $access;
		$this->type = $type;
		$this->static = $isStatic;
		$this->fromTrait = $fromTrait;
	}

	/**
	 * getName
	 *
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * getAccess
	 *
	 * @return string
	 */
	public function getAccess() {
		return $this->access;
	}

	/**
	 * getType
	 *
	 * @return string
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * isStatic
	 *
	 * @return bool
	 */
	public function isStatic() {
		return $this->static;
	}

	/**
	 * wasImportedFromTrait
	 * @return bool
	 */
	public function wasImportedFromTrait() {
		return !empty($this->fromTrait);

	}
}