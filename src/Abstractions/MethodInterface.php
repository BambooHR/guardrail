<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\Abstractions;

use BambooHR\Guardrail\Abstractions\FunctionLikeInterface;

interface MethodInterface extends FunctionLikeInterface {
	function isAbstract();
	function isStatic();
	function getAccessLevel();
}