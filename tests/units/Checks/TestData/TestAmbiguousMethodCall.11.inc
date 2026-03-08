<?php

class Foo {
	public function method() {}
}

function test(Foo $obj) {
	return $obj->method();
}
