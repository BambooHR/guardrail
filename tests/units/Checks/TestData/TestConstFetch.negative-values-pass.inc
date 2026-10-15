<?php declare(strict_types=1);

// User-defined constants are typed as mixed, including a negated int.
// strict_types does not reject mixed, so this call produces no signature error.

const TEST_CONST = -1;

function test(int $value) {
	if ($value > 0) {
		return true;
	}
	return false;
}

test(TEST_CONST); // mixed passed to int (TEST_CONST = -1)
