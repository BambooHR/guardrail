<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\Output;

use PhpParser\Node;

interface OutputInterface
{
	function emitError($className, $file, $line, $type, $message="");
	function output($verbose, $extraVerbose);
	function outputVerbose($string);
	function outputExtraVerbose($string);
	function incTests();
	function getErrorCount();
}