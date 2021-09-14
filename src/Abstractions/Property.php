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
	 * @param string $name     The name of the property
	 * @param string $type     The type of the property
	 * @param string $access   The access
	 * @param bool   $isStatic Is it static
	 */
	public function __construct(string $name,?string $type, string $access,bool $isStatic) {
		$this->name = $name;
		$this->access = $access;
		$this->type = ($type === null ? "" : $type);
		$this->static = $isStatic;
	}

	/**
	 * getName
	 *
	 * @return string
	 */
	public function getName():string {
		return $this->name;
	}

	/**
	 * getAccess
	 *
	 * @return string
	 */
	public function getAccess():string {
		return $this->access;
	}

	/**
	 * getType
	 *
	 * @return string
	 */
	public function getType():string {
		return $this->type;
	}

	/**
	 * isStatic
	 *
	 * @return bool
	 */
	public function isStatic():bool {
		return $this->static;
	}
}