<?php namespace BambooHR\Guardrail\Checks;

abstract class MyTestClass {
	public function checkStoredClass() {

	}
}

class MyOtherClass {

	public function checkStoredClass() {
		$classVar = 'BambooHR\\Guardrail\\Checks\\MyTestClass';
		$object = new $classVar;

		$otherNonClass = 'BambooHR\\Guardrail\\Checks\\WasHere';
	}
}