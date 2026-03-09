<?php

class ClassA {
	public function method() {}
}

class ClassB {
	public function method() {}
}

function test(ClassA|ClassB $obj) {
	return $obj->method();
}
