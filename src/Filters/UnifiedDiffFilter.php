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
				$fileName = array_pop( $parts );
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
	 * @param string $fileName    -
	 * @param int    $ignoreParts Number of top level directories to ignore
	 * @return UnifiedDiffFilter
	 */
	static function importFile($fileName, $ignoreParts = 1) {
		return new UnifiedDiffFilter( self::parse( file( $fileName ), $ignoreParts ) );
	}

	/**
	 * @return void
	 */
	function display() {
		foreach ($this->filter as $fileName => $lineNumbers) {
			echo "Filter: $fileName: ";
			
			// Stream output in chunks to avoid memory issues with large files
			$chunkSize = 100; // Adjust based on your needs
			$totalLines = count($lineNumbers);
			
			for ($i = 0; $i < $totalLines; $i += $chunkSize) {
				$chunk = array_slice($lineNumbers, $i, $chunkSize);
				echo implode(",", $chunk);
				
				// Flush output buffer to ensure content is sent to output
				if ($i + $chunkSize < $totalLines) {
					echo ",";
					flush();
				}
			}
			
			echo "\n";
			flush();
		}
	}

	/**
	 * @param string $fileName   The file being tested
	 * @param string $errorName  The name of the error
	 * @param int    $lineNumber The line number that the error was detected on.
	 * @return bool
	 */
	function shouldEmit($fileName, $errorName, $lineNumber) {
		return isset($this->filter[$fileName]) && in_array($lineNumber, $this->filter[$fileName]);
	}
}
