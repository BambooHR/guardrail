<?php

class MyService {
	// This method should emit 2 errors
	/**
	 * @param string $a
	 * @param int    $b
	 *
	 * @return int
	 */
	public function noParamTypes($a, $b): int {
		return 123;
	}

	// This method should emit 2 errors
	public function specialParamTypes(mixed $a, object $b): int {
		return 123;
	}

	// This method should emit 1 error
	public function unionParamTypes(int | string | object $a, int | float $b): int {
		return 123;
	}

	// This method should emit 0 errors
	public function intersectionParamTypes(MyObj&MyObj2 $a): int {
		return 123;
	}

	// This method should emit 0 errors
	public function withNormalTypedParams(bool $a, int $b, string $c): int {
		return 123;
	}
}

class MyObj {
}

class MyObj2 {
}