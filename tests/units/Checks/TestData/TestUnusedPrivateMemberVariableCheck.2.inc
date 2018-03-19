<?php

class MyTestClass {

	private $memberVariable = 'nothing';
	private $anotherVariable = 'another thing';

	public function __construct() {
		$this->memberVariable = 'should be ignored';
		$this->anotherVariable = 'should be ignored';
	}

	public function testClassFunction1() {
//		$this->memberVariable = 'no';
	}
}
