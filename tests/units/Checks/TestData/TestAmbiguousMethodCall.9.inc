<?php

class Foo {
	public function method() {}
}

function test(Foo|string $obj) {
	if (is_object($obj)) {
		return $obj->method();
	}
}
