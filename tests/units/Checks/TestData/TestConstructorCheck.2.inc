<?php

class ParentClass {
	public function __construct() {
	}
}

class ChildClass extends ParentClass {
	public function __construct() {
		parent::__construct();
	}
}
