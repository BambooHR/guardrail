<?php

class PropertyFetchCheckClassParent {
	private $private1;
	protected $protected1;

	public function __construct() {
		$this->private1 = 'private1';
		$this->protected1 = 'protected1';
	}

	public function testMethod1() {
		return;
	}
}

class PropertyFetchCheckClassChild extends PropertyFetchCheckClassParent {
	private $private2;
	protected $protected2;

	public function __construct() {
		parent::__construct();
		$this->private2 = 'private2';
		$this->protected2 = 'protected2';
	}

	public function __get($var) {
		return $this->$var;
	}

	public function testMethod2() {
		return;
	}
}

class PropertyFetchCheckClassGrandChild extends PropertyFetchCheckClassChild {
	private $private3;
	protected $protected3;

	public function __construct() {
		parent::__construct();
		$this->private3 = 'private3';
		$this->protected3 = 'protected3';
	}

	public function testMethod3() {
		return;
	}
}

$propertyFetchCheckClassParent = new PropertyFetchCheckClassParent();
$propertyFetchCheckClassParent->private1; //Should fail
$propertyFetchCheckClassParent->protected1; //Should fail
$propertyFetchCheckClassParent->unknown1; //Should fail
$propertyFetchCheckClassParent->testMethod1; //emits both TYPE_INCORRECT_DYNAMIC_CALL and TYPE_UNKNOWN_PROPERTY

$propertyFetchCheckClassChild = new PropertyFetchCheckClassChild();
$propertyFetchCheckClassChild->private1;
$propertyFetchCheckClassChild->protected1;
$propertyFetchCheckClassChild->private2;
$propertyFetchCheckClassChild->protected2;
$propertyFetchCheckClassChild->unknown1;
$propertyFetchCheckClassChild->unknown2;
$propertyFetchCheckClassChild->testMethod1;
$propertyFetchCheckClassChild->testMethod2;

$propertyFetchCheckClassGrandChild = new PropertyFetchCheckClassGrandChild();
$propertyFetchCheckClassGrandChild->private1;
$propertyFetchCheckClassGrandChild->protected1;
$propertyFetchCheckClassGrandChild->private2;
$propertyFetchCheckClassGrandChild->protected2;
$propertyFetchCheckClassGrandChild->private3;
$propertyFetchCheckClassGrandChild->protected3;
$propertyFetchCheckClassGrandChild->unknown1;
$propertyFetchCheckClassGrandChild->unknown2;
$propertyFetchCheckClassGrandChild->unknown3;
$propertyFetchCheckClassGrandChild->testMethod1;
$propertyFetchCheckClassGrandChild->testMethod2;
$propertyFetchCheckClassGrandChild->testMethod3;