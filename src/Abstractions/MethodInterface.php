<?php namespace BambooHR\Guardrail\Abstractions;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

/**
 * Interface MethodInterface
 *
 * @package BambooHR\Guardrail\Abstractions
 */
interface MethodInterface extends FunctionLikeInterface {

	/**
	 * isAbstract
	 *
	 * @return bool
	 */
	public function isAbstract():bool;

	/**
	 * isStatic
	 *
	 * @return bool
	 */
	public function isStatic():bool;

	/**
	 * getAccessLevel
	 *
	 * @return string
	 */
	public function getAccessLevel():string;

	/**
	 * @return bool
	 */
	public function hasNullableReturnType():bool;

	/**
	 * @return string
	 */
	public function getReturnType():string;
}