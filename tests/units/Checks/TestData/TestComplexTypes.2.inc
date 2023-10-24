<?php

interface Foo {
	function foo();
}

interface Bar {
	function bar();
}

class Baz implements Foo,Bar {

	function foo() { }
	function bar() { }
}


function foobar(Foo&Bar $fooBar) {
	$fooBar->foo();
	$fooBar->bar();
}

foobar(new Baz());