<?php

class PublicSomeClass {

}

class PublicParentClass {
	public function testMethod($item) {
		return;
	}
}

class PublicChildClass extends PublicParentClass {
	public function testMethod(PublicSomeClass $item1, ProtectedSomeClass $item2 = null) {
		return;
	}
}