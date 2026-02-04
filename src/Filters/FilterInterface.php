<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\Filters;

interface FilterInterface {
	/**
	 * @param string $fileName   The file being tested
	 * @param string $errorName  The name of the error
	 * @param int    $lineNumber The line the error occurred on.
	 * @return bool
	 */
	function shouldEmit($fileName, $errorName, $lineNumber);
}
