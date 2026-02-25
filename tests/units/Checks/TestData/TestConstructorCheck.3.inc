<?php

abstract class ParentClass {
	abstract public function __construct();
}

class ChildClass extends ParentClass {
	public function __construct() {
	}
}
