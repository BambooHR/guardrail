<?php

const TEST_CONST = -1;

function test(int $value) {
	// Should recognize TEST as int type
	if ($value > 0) {
		return true;
	}
	return false;
}

test(TEST_CONST);
