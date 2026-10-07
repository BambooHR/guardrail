<?php declare(strict_types=1);

// User-defined constants are typed as mixed, including a negated int.
// In strict mode, mixed is not compatible with int, so the call below is a
// signature error even though the constant evaluates to -1.

const TEST_CONST = -1;

function test(int $value) {
	return $value > 0;
}

test(TEST_CONST); // mixed passed to int (TEST_CONST = -1)
