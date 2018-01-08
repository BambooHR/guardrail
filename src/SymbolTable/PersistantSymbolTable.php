<?php
/**
 * Guardrail.  Copyright (c) 2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\SymbolTable;


interface PersistantSymbolTable {
	/**
	 * @return void
	 */
	public function connect();

	/**
	 * @return void
	 */
	public function disconnect();

	/**
	 * @return void
	 */
	function flushInserts();

	/**
	 * @return void
	 */
	function indexTable();
}