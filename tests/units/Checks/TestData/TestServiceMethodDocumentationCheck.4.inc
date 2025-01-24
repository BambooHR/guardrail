<?php

class MyService extends BaseService {
	// This method should emit 5 errors
	/**
	 * @param bool                   $a
	 * @param string |  float | bool $b
	 * @param string | float | bool  $c
	 * @param string | float         $d
	 *
	 * @return int
	 */
	public function mismatchUnionTypes(
		string | bool | float $a,
		float                 $b,
		bool | float          $c,
		string | bool         $d,
	): int | float {
		return 123;
	}

	// This method should emit 5 errors

	/**
	 * @param MyObj          $a
	 * @param MyObj & MyObj2 $b
	 * @param MyObj&MyObj2   $c
	 * @param MyObj&MyObj3   $d
	 *
	 * @return int | float
	 */
	public function mismatchIntersectionTypes(
		MyObj&MyObj2            $a,
		MyObj                   $b,
		MyObj & MyObj2 & MyObj3 $c,
		MyObj & MyObj2          $d,
	): float {
		return 123;
	}

	// This method should emit 3 errors?

	/**
	 * @param ?string $a
	 * @param int     $b
	 * @param int     $c
	 *
	 * @return float
	 */
	public function docBlockWithExtraTypes(string $a, int $b): int {
		return 123;
	}

	// This method should emit 0 errors

	/**
	 * @param ?string|null $a
	 * @param string|null $b
	 * @param ?string $c
	 *
	 * @return int|null
	 */
	public function nullableTypes(?string $a, ?string $b, string|null $c): ?int {
		return 123;
	}

	// This method should emit 2 errors for $b and $c

	/**
	 * @param MyObj[] $a
	 * @param ?MyObj2[] $b
	 * @param MyObj2[]|null $c
	 *
	 * @return MyObj3[]
	 */
	public function descriptiveArrayTypes(array $a, array $b, array $c): array {
		return [];
	}
}

class MyObj {
}

class MyObj2 {
}

class MyObj3 {
}