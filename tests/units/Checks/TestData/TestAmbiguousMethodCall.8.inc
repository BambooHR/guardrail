<?php

class Foo {
	public function method() {}
}

function test(Foo|mixed $obj) {
	return $obj->method();
}
