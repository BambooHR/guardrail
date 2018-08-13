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
	public function getParameters();

	/**
	 * getMinimumRequiredParameters
	 *
	 * @return mixed
	 */
	public function getMinimumRequiredParameters();

	/**
	 * getReturnType
	 *
	 * @return string
	 */
	public function getReturnType();

	/**
	 * hasNulllableReturnType
	 *
	 * @return bool
	 */
	public function hasNullableReturnType();

	/**
	 * getDocBlockReturnType
	 *
	 * @return string|null
	 */
	public function getDocBlockReturnType();

	/**
	 * isInternal
	 *
	 * @return bool
	 */
	public function isInternal();

	/**
	 * isDeprecated
	 *
	 * @return bool
	 */
	public function isDeprecated();

	/**
	 * getName
	 *
	 * @return string
	 */
	public function getName();

	/**
	 * getStartingLine
	 *
	 * @return int
	 */
	public function getStartingLine();

	/**
	 * isVariadic
	 *
	 * @return bool
	 */
	public function isVariadic();
}