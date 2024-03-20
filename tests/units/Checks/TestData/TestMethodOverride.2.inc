<?php

class Foo {
	function foo() {
		echo "Foo\n";
	}
}

class Bar {
	#[Override]
	function foo() {
		echo "Foo\n";
	}
} 