<?php namespace BambooHR\Guardrail\Filters;

use Webmozart\Glob\Glob;

class EmitFilterApplier {
    /**
	 * emitPatternMatches
	 *
	 * @param string $name    The name
	 * @param string $pattern The pattern
	 *
	 * @return bool
	 */
	static public function emitPatternMatches($name, $pattern) {
		if (substr($pattern, -2) == '.*') {
			$start = substr($pattern, 0, -2);
			return (strpos($name, $start) === 0);
		} else {
			return $name == $pattern;
		}
	}

	/**
	 * shouldEmit
	 *
	 * @param string          $fileName   The file name
	 * @param string          $name       The name
	 * @param int             $lineNumber The line number the error occurred on.
     * @param array           $emitList   A list of things that should be emitted
     * @param array           $silenced   A list of things to be silenced
     * @param FilterInterface $filter     A filter to be applied for emit entries limited to "new"
	 *
	 * @return bool
	 */
	static public function shouldEmit($fileName, $name, $lineNumber, $emitList, $silenced, ?FilterInterface $filter) {
		if (isset($silenced[$name]) && $silenced[$name] > 0) {
			return false;
		}
		$entry = static::findEmitEntry($emitList, $name);
		if ($entry === false) {
			return $entry;
		}
		if (
			isset($entry['when']) &&
			$entry['when'] == 'new' &&
			(
				!$filter ||
				!$filter->shouldEmit($fileName, $name, $lineNumber)
			)
		) {
			return false;
		}
		return true;
	}
	
	static public function findEmitEntry($emitList, $name) {
		foreach ($emitList as $entry) {
			if (
				is_array($entry)
			) {
				if (isset($entry['emit']) && !static::emitPatternMatches($name, $entry['emit'])) {
					continue;
				}
				if (isset($entry['glob']) && !Glob::match( "/" . $fileName, "/" . $entry['glob'])) {
					continue;
				}
				if (isset($entry['ignore']) && Glob::match("/" . $fileName, "/" . $entry['ignore'])) {
					continue;
				}
				return $entry;
				
			} else if (is_string($entry) && static::emitPatternMatches($name, $entry)) {
				return $entry;
			}
		}
		return false;
	}
}