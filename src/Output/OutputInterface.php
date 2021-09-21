<?php namespace BambooHR\Guardrail\Output;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */
/**
 * Interface OutputInterface
 *
 * @package BambooHR\Guardrail\Output
 */
interface OutputInterface {

	/**
	 * emitError
	 *
	 * @param string $className The name of the class
	 * @param string $file      The name of the file
	 * @param int    $line      The line number
	 * @param string $type      The type
	 * @param string $message   The message
	 *
	 * @return false|array
	 */
	function emitError(string $className, string $file, int $line, string $type, string $message = "");

	function shouldEmit(string $file, string $type, int $line):bool;

	function getEmitConfig(string $file, string $type, int $line);

	/**
	 * output
	 *
	 * @param string $verbose      Should use verbose mode
	 * @param string $extraVerbose Should be extra verbose
	 *
	 * @return mixed
	 */
	function output($verbose, $extraVerbose);

	/**
	 * outputVerbose
	 *
	 * @param string $string The verbose output
	 *
	 * @return mixed
	 */
	function outputVerbose($string);

	/**
	 * outputExtraVerbose
	 *
	 * @param string $string The extra verbose output
	 *
	 * @return mixed
	 */
	function outputExtraVerbose($string);

	/**
	 * incTests
	 *
	 * @return mixed
	 */
	function incTests();

	/**
	 * getErrorCount
	 *
	 * @return mixed
	 */
	function getErrorCount();

	/**
	 * silenceType
	 *
	 * @param string $name The name
	 *
	 * @return mixed
	 */
	function silenceType($name);

	/**
	 * resumeType
	 *
	 * @param string $name The resume type
	 *
	 * @return mixed
	 */
	function resumeType($name);
}