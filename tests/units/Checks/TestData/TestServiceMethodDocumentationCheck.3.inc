<?php
class BaseService {
}
class SubService extends BaseService {
}
class MyService extends SubService {
	// This method should emit 2 errors
	/**
	 * @param $a
	 * @param $b
	 *
	 * @return int
	 */
	public function noParamTypes($a, $b): int {
		return 123;
	}

	// This method should emit 1 error
	public function noDocBlock(string $a, int $b): int {
		return 123;
	}

	// This method should emit 0 errors
	/**
	 * @param string $a
	 * @param int    $b
	 *
	 * @return int
	 */
	public function matchingDocBlock(string $a, int $b): int {
		return 123;
	}

//	 This method should emit 2 errors

	/**
	 * @param bool $a
	 * @param int  $b
	 *
	 * @return float
	 */
	public function partialMatchingDocBlock(string $a, int $b): int {
		return 123;
	}

	// This method should emit 2 errors

	/**
	 * @param $a
	 */
	public function withNoDocReturn(int $a): float {
		return 123;
	}
}
