<?php namespace BambooHR\Guardrail\Abstractions;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

/**
 * Class FunctionLikeParameter
 *
 * @package BambooHR\Guardrail\Abstractions
 */
class FunctionLikeParameter {

	/**
	 * @var string
	 */
	private $type;

	/**
	 * @var string
	 */
	private $name;

	/**
	 * @var bool
	 */
	private $optional;

	/**
	 * @var bool
	 */
	private $reference;

	/**
	 * @var bool
	 */
	private $nullable;

	/**
	 * FunctionLikeParameter constructor.
	 *
	 * @param string $type      The type
	 * @param string $name      The name
	 * @param bool   $optional  Is it optional
	 * @param bool   $reference Is it a reference
	 * @param bool   $nullable  Is it nullable
	 */
	public function __construct($type, $name, $optional, $reference, $nullable) {
		$this->type = $type;
		$this->name = $name;
		$this->optional = $optional;
		$this->reference = $reference;
		$this->nullable = $nullable;
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
	 * getName
	 *
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * isOptional
	 *
	 * @return bool
	 */
	public function isOptional() {
		return $this->optional;
	}

	/**
	 * isReference
	 *
	 * @return bool
	 */
	public function isReference() {
		return $this->reference;
	}

	/**
	 * @return bool
	 */
	public function isNullable() {
		return $this->nullable;
	}
}