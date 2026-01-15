<?php declare(strict_types=1);

// Invalid void/never usage
class VoidNeverInvalid {
	function voidWithValue(): void {
		return 1;
	}

	function neverWithValue(): never {
		return 1;
	}

	function intWithEmptyReturn(): int {
		return;
	}
}
