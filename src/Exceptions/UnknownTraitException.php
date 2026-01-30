<?php 

namespace BambooHR\Guardrail\Exceptions;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

/**
 * Class UnknownTraitException
 *
 * @package BambooHR\Guardrail\Exceptions
 */
class UnknownTraitException extends \Exception {

	/**
	 * UnknownTraitException constructor.
	 *
	 * @param string $name The name
	 * @param int    $line The line number
	 */
	public function __construct($name, $line) {
		parent::__construct("Unknown trait $name imported on line $line");
	}
}