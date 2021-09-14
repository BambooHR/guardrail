<?php namespace BambooHR\Guardrail\Abstractions;

use PhpParser\Node\UnionType;

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
	 * @var string|UnionType
	 */
	private $type;

	/**
	 * @var string
	 */
	private string $name;

	/**
	 * @var bool
	 */
	private bool $optional;

	/**
	 * @var bool
	 */
	private bool $reference;

	/**
	 * @var bool
	 */
	private bool $nullable;

	/**
	 * FunctionLikeParameter constructor.
	 *
	 * @param string|UnionType $type The type
	 * @param string           $name The name
	 * @param bool             $optional Is it optional
	 * @param bool             $reference Is it a reference
	 * @param bool             $nullable Is it nullable
	 */
	public function __construct($type, string $name, bool $optional, bool $reference, bool $nullable) {
		$this->type = ($type === NULL ? "" : $type);
		$this->name = $name;
		$this->optional = $optional;
		$this->reference = $reference;
		$this->nullable = $nullable;
	}

	/**
	 * getType
	 *
	 * @return string|UnionType
	 */
	public function getType() {
		return $this->type;
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
	 * isOptional
	 *
	 * @return bool
	 */
	public function isOptional():bool {
		return $this->optional;
	}

	/**
	 * isReference
	 *
	 * @return bool
	 */
	public function isReference():bool {
		return $this->reference;
	}

	/**
	 * @return bool
	 */
	public function isNullable():bool {
		return $this->nullable;
	}
}