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
	 * Property constructor.
	 *

	 */
	public function __construct(string $name,?\PhpParser\Node $type, string $access, string $isStatic) {
		$this->name = $name;
		$this->access = $access;
		$this->type = $type;
		$this->static = $isStatic;
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
}