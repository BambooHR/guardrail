<?php 

namespace BambooHR\Guardrail\Abstractions;

use PhpParser\Node\ComplexType;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;

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

	public function getComplexReturnType();

	/**
	 * hasNulllableReturnType
	 *
	 * @return bool
	 */
	public function hasNullableReturnType();

	/**
	 * getDocBlockReturnType
	 *
	 * @return ComplexType|Name|Identifier|null
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

	public function getThrowsList():array;
}