<?php namespace BambooHR\Guardrail\Abstractions;

use PhpParser\Node\Expr;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

/**
 * Interface ClassInterface
 *
 * @package BambooHR\Guardrail\Abstractions
 */
interface ClassInterface {

	/**
	 * getName
	 *
	 * @return string
	 */
	public function getName();

	/**
	 * isDeclaredAbstract
	 *
	 * @return bool
	 */
	public function isDeclaredAbstract();

	/**
	 * getMethodNames
	 *
	 * @return array
	 */
	public function getMethodNames();

	/**
	 * getParentClassName
	 *
	 * @return string
	 */
	public function getParentClassName();

	/**
	 * getInterfaceNames
	 *
	 * @return array
	 */
	public function getInterfaceNames();

	/**
	 * getMethod
	 *
	 * @param string $name The name of the method
	 *
	 * @return ClassMethod|null
	 */
	public function getMethod($name);

	/**
	 * getProperty
	 *
	 * @param string $name The name of the property
	 *
	 * @return Property;
	 */
	public function getProperty($name);

	/**
	 * getPropertyNames
	 *
	 * @return array
	 */
	public function getPropertyNames();

	/**
	 * hasConstant
	 *
	 * @param string $name The name of the constant
	 *
	 * @return bool
	 */
	public function hasConstant($name);

	public function getConstantExpr($name):?Expr;

	/**
	 * isInterface
	 *
	 * @return bool
	 */
	public function isInterface();
}