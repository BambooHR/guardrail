<?php namespace BambooHR\Guardrail\Checks;

class MyTestClass {

	public function __construct() {

	}

	public function checkStoredClass() {
		$classVar = 'MyTestClass';
		$object = new $classVar;
	}
}