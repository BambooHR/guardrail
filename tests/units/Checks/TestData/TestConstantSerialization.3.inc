<?php

class TestStringConcatenation {
	const MY_PATH = __DIR__ . '/../Data/file.json';

	public function test(string $value) {
		// Should recognize MY_PATH as string type
		if (file_exists(self::MY_PATH)) {
			return true;
		}
		return false;
	}
}

$test = new TestStringConcatenation();
$test->test(__DIR__ . '/../Data/file.json');
$test->test(TestStringConcatenation::MY_PATH);
