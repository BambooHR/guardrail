<?php namespace BambooHR\Guardrail\Abstractions;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

/**
 * Interface FunctionLikeInterface
 *
 * @package BambooHR\Guardrail\Abstractions
 */
interface FunctionLikeInterface {

	/**
	 * getParameters
	 *
	 * @return FunctionLikeParameter[]
	 */
	public function getParameters():array;

	/**
	 * getMinimumRequiredParameters
	 *
	 * @return int
	 */
	public function getMinimumRequiredParameters():int;

	/**
	 * getReturnType
	 *
	 * @return string
	 */
	public function getReturnType():string;

	/**
	 * hasNulllableReturnType
	 *
	 * @return bool
	 */
	public function hasNullableReturnType():bool;

	/**
	 * getDocBlockReturnType
	 *
	 * @return string|null
	 */
	public function getDocBlockReturnType():?string;

	/**
	 * isInternal
	 *
	 * @return bool
	 */
	public function isInternal():bool;

	/**
	 * isDeprecated
	 *
	 * @return bool
	 */
	public function isDeprecated():bool;

	/**
	 * getName
	 *
	 * @return string
	 */
	public function getName():string;

	/**
	 * getStartingLine
	 *
	 * @return int
	 */
	public function getStartingLine():int;

	/**
	 * isVariadic
	 *
	 * @return bool
	 */
	public function isVariadic():bool;
}