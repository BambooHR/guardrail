<?php

class TestArrayConstant {
	const MY_ARRAY = [1, 2, 3];

	public function test(array $value) {
		// Should recognize MY_ARRAY as array type
		if (in_array(1, self::MY_ARRAY)) {
			return true;
		}
		return false;
	}
}

$test = new TestArrayConstant();
$test->test([1, 2, 3]);
$test->test(TestArrayConstant::MY_ARRAY);
