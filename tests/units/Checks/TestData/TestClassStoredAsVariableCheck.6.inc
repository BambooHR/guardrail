<?php namespace BambooHR\Guardrail\Checks;

abstract class MyTestClass {
	public function checkStoredClass() {

	}
}

class MyOtherClass {

	public function checkStoredClass() {
		$classVar = 'MyTestClasses';
		$object = new $classVar;

		$class = new BambooHR\Guardrail\Checks\MyTestClass();
	}
}