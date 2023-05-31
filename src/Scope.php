<?php namespace BambooHR\Guardrail;

/**
 * Guardrail.  Copyright (c) 2016-2023, BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Scope\PluginScopeInterface;
use BambooHR\Guardrail\Scope\ScopeVar;
use PhpParser\Node;
use PhpParser\Node\ComplexType;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;

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

}