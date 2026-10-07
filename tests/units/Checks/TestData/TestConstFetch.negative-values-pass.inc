<?php declare(strict_types=0);

// User-defined constants are typed as mixed, including a negated int.
// With strict_types=0, a mixed argument is compatible with int, so this call
// produces no signature error.

const TEST_CONST = -1;

function test(int $value) {
	if ($value > 0) {
		return true;
	}
	return false;
}

test(TEST_CONST); // mixed passed to int (TEST_CONST = -1); allowed because strict_types=0
