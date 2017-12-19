<?php

class ProtectedSomeClass {

}

class ProtectedParentClass {
	protected function testMethod($item) {
		return;
	}
}

class ProtectedChildClass extends ProtectedParentClass {
	protected function testMethod(ProtectedSomeClass $item1, ProtectedSomeClass $item2 = null) {
		return;
	}
}