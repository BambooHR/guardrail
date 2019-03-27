<?php

namespace Testing {
	function foo() {
		echo "Foo\n";
	}
	foo();
}

namespace Otherspace{
	function bar() {
		echo "Bar\n";
	}
	\Testing\foo();
	\Otherspace\bar();
}

namespace {
	function baz() {
		echo "Baz\n";
	}
	\Otherspace\bar();
	baz();
}