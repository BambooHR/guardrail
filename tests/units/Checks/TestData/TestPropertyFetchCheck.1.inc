<?php

class PropertyFetchCheckClass1 {
	private $private;
	protected $protected;

	public function __construct() {
		$this->private = 'private';
		$this->protected = 'protected';
	}

	public function testMethod() {
		return;
	}
}

$propertyFetchCheckClass1 = new PropertyFetchCheckClass1();
$propertyFetchCheckClass1->private;
$propertyFetchCheckClass1->protected;
$propertyFetchCheckClass1->unknown;
$propertyFetchCheckClass1->testMethod; //emits both TYPE_INCORRECT_DYNAMIC_CALL and TYPE_UNKNOWN_PROPERTY