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
	 * @param string $str The string to parse
	 * @return \PhpParser\Node\Stmt[]
	 * @guardrail-ignore Standard.Unknown.Function, Standard.Unknown.Class
	 */
	function parse($str) {
		try {
			return $this->reverter->convertAstNodeArray(\ast\parse_code($str, 50));
		} catch (\ParseError $ex) {
			return [];
		}
	}
}