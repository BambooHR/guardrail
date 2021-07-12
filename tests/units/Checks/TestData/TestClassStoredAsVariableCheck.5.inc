<?php

abstract class MyTestClass {
	public function checkStoredClass() {

	}
}

class MyOtherClass {

	public function checkStoredClass() {
		$classVar = 'MyTestClasses';
		$object = new $classVar;

		$class = new MyTestClass();

		$partialNonMatching = 'Class';
	}
}