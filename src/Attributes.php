<?php namespace BambooHR\Guardrail;

/**
 * Guardrail.  Copyright (c) 2016-2018, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */
class Attributes {
	// Eager terms, these spread if any of the terms pass them.
	const TOUCHED_USER_INPUT     = 0x0001;
	const TOUCHED_FUNCTION_PARAM = 0x0002;
	const NULL_POSSIBLE          = 0x0004;


	// Combining terms, these only spread if both terms have them.
	const CLEAN_URL               = 0x0100;
	const CLEAN_DB_TERM           = 0x0200;
	const CLEAN_TABLE_NAME        = 0x0400;
	const CLEAN_HTML              = 0x0800;
	const IS_CONST                = 0x1000;

	/**
	 * Combines data from two different masks so that all eager terms exist in the result and combining terms are included
	 * if they are set in both masks.
	 *
	 * @param int $mask1 -
	 * @param int $mask2 -
	 * @return int
	 */
	static function combine($mask1, $mask2) {
		return (($mask1 | $mask2) & 0xFF) | (($mask1 & $mask2) & 0xFF00);
	}
}