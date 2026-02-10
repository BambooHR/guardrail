<?php

class TestMagicConstant {
	const MY_DIR = __DIR__;

	public function test(string $value) {
		// Should recognize MY_DIR as string type
		if (file_exists(self::MY_DIR)) {
			return true;
		}
		return false;
	}
}

$test = new TestMagicConstant();
$test->test(__DIR__);
$test->test(TestMagicConstant::MY_DIR);
