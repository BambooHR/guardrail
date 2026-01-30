<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\Filters;

use const DIRECTORY_SEPARATOR;

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
	 * @param array $lines       An array of strings representing the lines of a file.
	 * @param int   $ignoreParts Number of top level directories to ignore
	 * @return array A nested array of ["file"=>[line numbers], ... ]
	 */
	static function parse($lines, $ignoreParts = 1) {
		$fileName = "";
		$fileNameArr = [];
		$filter = [];
		$lineNumbers = [];
		foreach ($lines as $line) {
			if (preg_match('!^\+\+\+ (\S+)!', $line, $fileNameArr)) {
				$parts = explode(DIRECTORY_SEPARATOR, $fileNameArr[1], $ignoreParts + 1);
				$fileName = array_pop($parts);
			} elseif (preg_match("!^@@ -\d+(,\d+)? \+(\d+)(,(\d+))?!", $line, $lineNumbers)) {
				$start = $lineNumbers[2];
				if (isset($lineNumbers[4])) {
					$end = $start + $lineNumbers[4] - 1;
					$filter[$fileName][] = [$start, $end];
				} else {
					$filter[$fileName][] = [$start, $start];
				}
			}
		}
		return $filter;
	}

	function binary_search($fileName, $lineNumber): bool {
		if (!isset($this->filter[$fileName])) {
			return false;
		}
		$lineNumbers = $this->filter[$fileName];
		$left = 0;
		$right = count($lineNumbers) - 1;
		while ($left <= $right) {
			$mid = floor(($left + $right) / 2);
			[$min, $max] = $lineNumbers[$mid];
			if ($lineNumber >= $min && $lineNumber <= $max) {
				return true;
			} elseif ($min < $lineNumber) {
				$left = $mid + 1;
			} else {
				$right = $mid - 1;
			}
		}
		return false;
	}

	/**
	 * @param string $fileName    -
	 * @param int    $ignoreParts Number of top level directories to ignore
	 * @return UnifiedDiffFilter
	 */
	static function importFile($fileName, $ignoreParts = 1) {
		return new UnifiedDiffFilter(self::parse(file($fileName), $ignoreParts));
	}

	/**
	 * @return void
	 */
	function display() {
		foreach ($this->filter as $fileName => $lineNumbers) {

			echo "Filter: $fileName: " .
				implode(
                    ",",
					array_map(
						fn($lineNumberPair) => $lineNumberPair[0] . "-" . $lineNumberPair[1],
						$lineNumbers
					)
				) . "\n";
		}
	}

	/**
	 * @param string $fileName   The file being tested
	 * @param string $errorName  The name of the error
	 * @param int    $lineNumber The line number that the error was detected on.
	 * @return bool
	 */
	function shouldEmit($fileName, $errorName, $lineNumber) {
		return $this->binary_search($fileName, $lineNumber);
	}
}
