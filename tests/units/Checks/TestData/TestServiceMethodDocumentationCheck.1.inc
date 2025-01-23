<?php

class MyService {
	// This method should emit 2 errors
	/**
	 * @param int    $a
	 * @param string $b
	 *
	 * @return int
	 */
	public function noParamTypes($a, $b): int {
		return 123;
	}

	// This method should emit 2 errors

	/**
	 * @param mixed  $a
	 * @param object $b
	 *
	 * @return int
	 */
	public function specialParamTypes(mixed $a, object $b): int {
		return 123;
	}

	// This method should emit 1 error

	/**
	 * @param int|string|object $a
	 * @param int|float         $b
	 *
	 * @return int
	 */
	public function unionParamTypes(int | string | object $a, int | float $b): int {
		return 123;
	}

	// This method should emit 0 errors

	/**
	 * @param MyObj2&MyObj $a
	 *
	 * @return int
	 */
	public function intersectionParamTypes(MyObj&MyObj2 $a): int {
		return 123;
	}

	// This method should emit 0 errors

	/**
	 * @param bool   $a
	 * @param int    $b
	 * @param string $c
	 *
	 * @return int
	 */
	public function withNormalTypedParams(bool $a, int $b, string $c): int {
		return 123;
	}
}

class MyObj {
}

class MyObj2 {
}