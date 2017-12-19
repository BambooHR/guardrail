<?php

class PrivateSomeClass {

}

class PrivateParentClass {
	private function testMethod($item) {
		return;
	}
}

class PrivateChildClass extends PrivateParentClass {
	private function testMethod(PrivateSomeClass $item1, PrivateSomeClass $item2 = null) {
		return;
	}
}