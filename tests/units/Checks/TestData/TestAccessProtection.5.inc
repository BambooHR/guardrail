<?php

class Foo {
	protected $protectedVar;
	function bar() {
		return $this->protectedVar;
	}
}

class Boo {
	function baz(Foo $f) {
		return $f->protectedVar;
	}
}
