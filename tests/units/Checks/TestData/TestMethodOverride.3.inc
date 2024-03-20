<?php

class Foo {
	function foo() {
		echo "Foo\n";
	}
}

class Bar extends Foo {
	#[Override]
	function bar() {
		echo "Foo\n";
	}
} 