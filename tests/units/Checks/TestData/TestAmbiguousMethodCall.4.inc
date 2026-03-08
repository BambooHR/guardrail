<?php

interface FooInterface {
	public function method();
}

class FooImpl implements FooInterface {
	public function method() {}
}

function test(FooInterface|FooImpl $obj) {
	return $obj->method();
}
