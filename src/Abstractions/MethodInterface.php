<?php 

namespace BambooHR\Guardrail\Abstractions;

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
	public function isAbstract();

	/**
	 * isStatic
	 *
	 * @return bool
	 */
	public function isStatic();

	/**
	 * getAccessLevel
	 *
	 * @return string
	 */
	public function getAccessLevel();

	/**
	 * @return bool
	 */
	public function hasNullableReturnType();


	public function getComplexReturnType();

	public function getAttributes(string $name):array;

}