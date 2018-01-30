<?php

class Foo {

	public $publicVar;
}

class Baz extends Foo {
	function foo() {
		return $this->publicVar;
	}
}

$a = new Foo();
echo $a->publicVar;