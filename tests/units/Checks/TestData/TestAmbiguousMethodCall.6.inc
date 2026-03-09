<?php

class TypeA {
	public function method() {}
}

class TypeB {
	public function method() {}
}

class TypeC {
	public function method() {}
}

function test(TypeA|TypeB|TypeC $obj) {
	return $obj->method();
}
