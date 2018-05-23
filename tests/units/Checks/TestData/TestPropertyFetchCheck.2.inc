<?php

class PropertyFetchCheckClass2 {
	private $private;
	protected $protected;

	public function __construct() {
		$this->private = 'private';
		$this->protected = 'protected';
	}

	public function __get($var) {
		return $this->$var;
	}

	public function testMethod() {
		return;
	}
}

$propertyFetchCheckClass1 = new PropertyFetchCheckClass2();
$propertyFetchCheckClass1->private;
$propertyFetchCheckClass1->protected;
$propertyFetchCheckClass1->unknown;
$propertyFetchCheckClass1->testMethod;