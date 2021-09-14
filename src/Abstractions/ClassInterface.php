<?php namespace BambooHR\Guardrail\Abstractions;

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
	public function getName():string;

	/**
	 * isDeclaredAbstract
	 *
	 * @return bool
	 */
	public function isDeclaredAbstract():bool;

	/**
	 * getMethodNames
	 *
	 * @return array
	 */
	public function getMethodNames():array;

	/**
	 * getParentClassName
	 *
	 * @return string
	 */
	public function getParentClassName():string;

	/**
	 * getInterfaceNames
	 *
	 * @return array
	 */
	public function getInterfaceNames():array;

	/**
	 * getMethod
	 *
	 * @param string $name The name of the method
	 *
	 * @return ClassMethod|null
	 */
	public function getMethod(string $name):?MethodInterface;

	/**
	 * getProperty
	 *
	 * @param string $name The name of the property
	 *
	 * @return Property;
	 */
	public function getProperty(string $name):?Property;

	/**
	 * getPropertyNames
	 *
	 * @return array
	 */
	public function getPropertyNames():array;

	/**
	 * hasConstant
	 *
	 * @param string $name The name of the constant
	 *
	 * @return bool
	 */
	public function hasConstant(string $name):bool;

	/**
	 * isInterface
	 *
	 * @return bool
	 */
	public function isInterface():bool;
}