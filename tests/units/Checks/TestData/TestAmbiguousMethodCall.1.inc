<?php

class A {
	public function method() {}
}

class B extends A {}

class C extends B {}

function test(A|B|C $obj) {
	return $obj->method();
}
