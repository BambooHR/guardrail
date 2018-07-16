<?php

/**
 * Guardrail.  Copyright (c) 2018, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail;

class PhpAstParser {
	/** @var PhpAstToPhpParser */
	private $reverter;

	/**
	 * PhpAstParser constructor.
	 */
	function __construct() {
		$this->reverter = new \BambooHR\Guardrail\PhpAstToPhpParser();
		$this->reverter->skipMethodBodies();
	}

	/**
	 * @return bool
	 */
	static function isSupported() {
		return function_exists('ast\\parse_code');
	}

	/**
	 * @param $str
	 * @return \PhpParser\Node\Stmt[]
	 */
	function parse($str) {
		return $this->reverter->convertAstNodeArray(\ast\parse_code($str,50));
	}
}