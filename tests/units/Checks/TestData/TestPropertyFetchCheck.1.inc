<?php

class ParentPropertyFetchCheckClass1 {

}
class PropertyFetchCheckClass1 extends ParentPropertyFetchCheckClass1 {
	private $private;
	protected $protected;
	public $public;

	public function __construct() {
		$this->private = 'private';
		$this->protected = 'protected';
		$this->public = 'public';
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

class testClass {
	function getPropertyFetchCheckClass1(): ParentPropertyFetchCheckClass1 {
		return new PropertyFetchCheckClass1();
	}
	function testFunc() {
		$parent = $this->getPropertyFetchCheckClass1();
		/** @var PropertyFetchCheckClass1 $parent */
		$parent->public;
	}
}
