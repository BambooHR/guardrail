<?php

class TestClass {
	protected function logException(ParentClass $parentClass) {
		return $parentClass instanceof ChildClass && $parentClass->test();
	}
}

class ParentClass {

}

class ChildClass extends ParentClass {
	function test(): bool {
		return true;
	}
}
