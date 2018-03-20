<?php

class MyTestClass {

	public function __construct() {

	}

	public function checkStoredClass() {
		$classVar = 'MyTestClass';
		$object = new $classVar;
	}
}