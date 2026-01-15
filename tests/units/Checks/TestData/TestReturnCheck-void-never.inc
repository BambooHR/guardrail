<?php

// Valid void/never usage
class VoidNeverValid {
	function voidWithEmptyReturn(): void {
		return;
	}

	function voidWithNoReturn(): void {
		// No return statement
	}
}

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
