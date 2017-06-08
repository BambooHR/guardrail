<?php namespace BambooHR\Guardrail;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use PhpParser\Node\Stmt\Do_;

/**
 * Class DoWhileStatement
 *
 * @package BambooHR\Guardrail
 */
class DoWhileStatement extends Do_ {

	/**
	 * fromDo
	 *
	 * @param Do_ $from Instance of Do_
	 *
	 * @return DoWhileStatement
	 */
	static public function fromDo(Do_ $from) {
		return new DoWhileStatement($from->cond, $from->stmts, $from->attributes);
	}

	/**
	 * getSubNodeNames
	 *
	 * @return array
	 */
	public function getSubNodeNames() {
		return ["stmts", "cond"];
	}
}