<?php

class MyTestClass {

	private $unusedProperty = 'my var';
	private $usedProperty = 'my var 2';

	public function __construct() {
		$this->unusedProperty = 'unused';
		$this->usedProperty = 'used';
	}

	public function method($unusedParam) {
		$unusedVar = '123';
		$usedVar = $this->usedProperty;
		return $usedVar;
	}


	public function test(array $arr) {
		return;
	}
}

(new MyTestClass())->method("test");