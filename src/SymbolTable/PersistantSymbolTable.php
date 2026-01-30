<?php

/**
 * Guardrail.  Copyright (c) 2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\SymbolTable;

interface PersistantSymbolTable {
	/**
	 * @param int $processNumber Used for persistence per thread.
	 * @return void
	 */
	public function connect($processNumber);

	/**
	 * @return void
	 */
	public function disconnect();

	/**
	 * @return void
	 */
	function flushInserts();

	/**
	 * @param int $processCount The number of child processes.
	 * @return void
	 */
	function indexTable($processCount);
}
