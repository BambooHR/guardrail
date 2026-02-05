<?php

class TestClass {
	const TEST = -1;

	public function test(int $value) {
		// Should recognize TEST as int type
		if (self::TEST > 0) {
			return true;
		}
		return false;
	}
}

$test = new TestClass();
$test->test(-1);
$test->test(TestClass::TEST);

const TEST_CONST = -1;

function test(int $value) {
	// Should recognize TEST as int type
	if ($value > 0) {
		return true;
	}
	return false;
}

test(TEST_CONST);
