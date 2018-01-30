<?php

class Foo {

	protected $protectedVAr;
}

class Baz extends Foo {
	function foo() {
		return $this->protectedVar;
	}
}
