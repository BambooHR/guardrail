<?php

interface InterfaceParent {}
interface InterfaceChild extends InterfaceParent {}

class ParentClassWithoutUnionMethodParameter {
	public function testMethod(InterfaceParent $interface) {
		return;
	}
}

class ChildClassWithUnionMethodParameter extends ParentClassWithoutUnionMethodParameter {
	public function testMethod(InterfaceChild|InterfaceParent $interface) {
		return;
	}
}

class ParentClassWithUnionMethodParameter {
	public function testMethod(InterfaceChild|InterfaceParent $interface) {
		return;
	}
}

class ChildClassWithoutUnionMethodParameter extends ParentClassWithUnionMethodParameter {
	public function testMethod(InterfaceParent $interface) {
		return;
	}
}