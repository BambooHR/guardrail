<?php

class Base {
	public function method() {}
}

class Child extends Base {}

class Unrelated {
	public function method() {}
}

function test(Base|Child|Unrelated $obj) {
	return $obj->method();
}
