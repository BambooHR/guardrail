<?php

class MyTestClass {

	const TEST_CLASS_CONSTANT = 'Setting';

	public function __construct() {
		$something = parent::TEST_CLASS_CONSTANT;
	}
}
