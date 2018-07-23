<?php

class FooParent {
	function baz() {
		echo "Here\n";
	}
}

class Foo extends FooParent {
	static function bar() {
		$a = function() {
			parent::baz();
		};
		$a();
	}

}


$a=new Foo();
$a->bar();
