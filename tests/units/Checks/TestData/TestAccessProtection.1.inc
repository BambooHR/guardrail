<?php

class Foo {

	private $privateVar;
}

class Baz extends Foo {
	function foo() {
		return $this->privateVar;
	}
}
