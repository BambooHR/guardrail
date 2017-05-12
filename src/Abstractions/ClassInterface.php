<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\Abstractions;


interface ClassInterface {
	function getName();
	function isDeclaredAbstract();
	function getMethodNames();
	function getParentClassName();
	function getInterfaceNames();
	function getMethod($name);
	function getProperty($name);
	function getPropertyNames();
	function hasConstant($name);
	function isInterface();
}