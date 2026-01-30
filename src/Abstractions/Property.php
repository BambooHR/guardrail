<?php

namespace BambooHR\Guardrail\Abstractions;

use PhpParser\Node;

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
	 * @var \PhpParser\Node
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


	private bool $isStatic;

	private bool $isReadOnly;

	/**
	 * Property constructor.
	 *

	 */
	public function __construct(private ClassInterface $cls, string $name, ?\PhpParser\Node $type, string $access, bool $isStatic, bool $isReadOnly) {
		$this->name = $name;
		$this->access = $access;
		$this->type = $type;
		$this->isStatic = $isStatic;
		$this->isReadOnly = $isReadOnly;
	}

	/**
	 * getName
	 *
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	public function getClass(): ClassInterface {
		return $this->cls;
	}

	/**
	 * getAccess
	 *
	 * @return string
	 */
	public function getAccess() {
		return $this->access;
	}

	public function isReadOnly() {
		return $this->isReadOnly;
	}

	/**
	 * getType
	 *
	 * @return Node
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
		return $this->isStatic;
	}
}