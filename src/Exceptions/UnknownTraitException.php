<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\Exceptions;

class UnknownTraitException extends \Exception {


	/**
	 * UnknownTraitException constructor.
	 */
	public function __construct($name, $file, $line) {
		parent::__construct("Unknown trait $name imported in file $file on line $line");
	}
}