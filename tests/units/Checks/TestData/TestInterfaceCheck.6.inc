<?php

class PrivateParentClassCheck6 {
	private function testMethod() {
		return;
	}
}

class ProtectedParentClassCheck6 extends PrivateParentClassCheck6 {
	protected function testMethod() {
		return;
	}
}

class PublicChildClassCheck6 extends ProtectedParentClassCheck6 {
	public function testMethod() {
		return;
	}
}