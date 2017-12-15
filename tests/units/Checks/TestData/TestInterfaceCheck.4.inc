<?php

class AnotherTestClass {

}

class PrivateParentClassCheck4 {
	private function testMethod($item) {
		return;
	}
}

class PublicChildClassCheck4 extends PrivateParentClassCheck4 {
	public function testMethod(AnotherTestClass $item1, AnotherTestClass $item2 = null) {
		return;
	}
}