<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\Filters;


class UnifiedDiffFilter implements FilterInterface {
	/**
	 * @var array
	 */
	private $filter = [];

	/**
	 * UnifiedDiffFilter constructor.
	 * @param array $filter A nested array of ["file"=>[line numbers], ... ]
	 */
	function __construct($filter) {
		$this->filter = $filter;
	}

	/**
	 * @param array $lines An array of strings representing the lines of a file.
	 * @return array A nested array of ["file"=>[line numbers], ... ]
	 */
	static function parse($lines) {
		$fileName = "";
		$fileNameArr = [];
		$filter = [];
		$lineNumbers = [];
		foreach ($lines as $line) {
			if (preg_match('!^\+\+\+ (\S+)!', $line, $fileNameArr)) {
				$fileName = $fileNameArr[1];
			} else if (preg_match("!^@@ -\d+(,\d+)? \+(\d+)(,(\d+))?!", $line, $lineNumbers)) {
				$start = $lineNumbers[2];
				if (isset($lineNumbers[4])) {
					$end = $start + $lineNumbers[4] - 1;
				} else {
					$end = $start;
				}
				for ($line = $start; $line <= $end; ++$line) {
					$filter[$fileName][] = $line;
				}
			}
		}
		return $filter;
	}

	/**
	 * @param $fileName
	 * @return UnifiedDiffFilter
	 */
	static function importFile($fileName) {
		return new UnifiedDiffFilter( self::parse( file( $fileName ) ) );
	}

	/**
	 * @param string $fileName
	 * @param string $errorName
	 * @param int    $lineNumber
	 * @return bool
	 */
	function shouldEmit($fileName, $errorName, $lineNumber) {
		return isset($this->filter[$fileName]) && in_array($lineNumber, $this->filter[$fileName]);
	}
}