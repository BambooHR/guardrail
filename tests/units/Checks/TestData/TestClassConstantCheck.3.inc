<?php

class MyTestClass {

	const TEST_CLASS_CONSTANT = 'Setting';

	public function __construct() {

	}
}

$testClass = new MyTestClass();
$something = self::TEST_CLASS_CONSTANT;
