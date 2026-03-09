<?php

class Foo {
	public function method() {}
}

function test(?Foo $obj) {
	if ($obj !== null) {
		return $obj->method();
	}
}
