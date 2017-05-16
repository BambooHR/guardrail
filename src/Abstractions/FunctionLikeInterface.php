<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\Abstractions;

use BambooHR\Guardrail\Abstractions\FunctionLikeParameter;

interface FunctionLikeInterface {
	/** @return FunctionLikeParameter[] */
	function getParameters();
	function getMinimumRequiredParameters();
	function getReturnType();
	function getDocBlockReturnType();
	function isInternal();
	function isDeprecated();

	function getName();


	function getStartingLine();
	function isVariadic();
}