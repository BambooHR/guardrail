<?php

class Foo {
	function foo() {
		echo "Foo\n";
	}
}

class Bar extends Foo
{
	#[Override]
	function foo() {
		echo "Foo\n";
	}
}