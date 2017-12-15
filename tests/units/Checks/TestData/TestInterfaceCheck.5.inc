<?php

class AnotherTestClass5 {

}

class PublicParentClassCheck5 {
	public function testMethod($item) {
		return;
	}
}

class PrivateChildClassCheck5 extends PublicParentClassCheck5 {
	private function testMethod(AnotherTestClass5 $item1, AnotherTestClass5 $item2 = null) {
		return;
	}
}