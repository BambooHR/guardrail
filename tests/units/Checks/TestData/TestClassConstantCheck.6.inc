<?php

class MyTestClass {

	const TEST_CLASS_CONSTANT = 'Setting';

	public function __construct() {
		$test = SomeUnknownClass::TEST_CLASS_CONSTANT;
	}
}

$testClass = new MyTestClass();
