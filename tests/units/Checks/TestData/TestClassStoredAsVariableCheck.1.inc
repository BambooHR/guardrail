<?php

class MyTestClass {

	public $variable;

	public function __construct() {
		$this->variable = 'MyTestClass';
	}

	public function checkStoredClass() {
		$classVar = 'MyTestClass';
		$object = new $classVar;
	}
}