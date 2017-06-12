<?php

class MyTestClass {

	const TEST_CLASS_CONSTANT = 'Setting';

	public function __construct() {
		$test = self::TEST_UNKNOWN;
	}
}

$testClass = new MyTestClass();
