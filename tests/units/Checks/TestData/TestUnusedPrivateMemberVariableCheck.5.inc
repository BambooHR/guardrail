<?php

class MyTestClass {

	private $memberVariable = 'my var';
	private $anotherVariable = 'another var';

	public function __construct() {
		$this->memberVariable = 'hello';
		$this->anotherVariable = 'welcome';
	}

	public function testClassFunction1() {
		$assigned1 = $this->anotherVariable;
		$assigned2 = $this->memberVariable;
	}
}