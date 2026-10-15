<?php declare(strict_types=1);

// A negated int constant is an int, so this call matches the parameter.

const TEST_CONST = -1;

function test(int $value) {
	if ($value > 0) {
		return true;
	}
	return false;
}

test(TEST_CONST); // int (TEST_CONST = -1)
