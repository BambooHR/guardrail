<?php namespace BambooHR\Guardrail;

/**
 * Guardrail.  Copyright (c) 2016-2023, BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Scope\PluginScopeInterface;
use PhpParser\Node;

/**
 * Class Scope
 *
 * @package BambooHR\Guardrail
 */
interface Scope extends PluginScopeInterface {
	/** @return Node[] */
	function getParentNodes():array;

	function getCurrentFile():string;

	public function setVarUsed($name):void;

	public function getConfig(): Config;
}