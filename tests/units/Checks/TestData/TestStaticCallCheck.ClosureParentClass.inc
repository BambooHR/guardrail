<?php

class FooParent {
	function baz() {
		echo "Here\n";
	}
}

class Foo extends FooParent {
	function bar() {
		$a = function() {
			parent::baz();
		};
		$a();
	}

}


$a=new Foo();
$a->bar();
