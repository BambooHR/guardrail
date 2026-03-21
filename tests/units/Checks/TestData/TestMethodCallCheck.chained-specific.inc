<?php

class Foo {
	/**
	 * @return Foo
	 */
	function chainableFoo() {
		return $this;
	}
}

function testExactScenario() {
	// This is the exact scenario from the user's description
	$var = (new Foo())->chainableFoo();
	$var->chainableFoo(); // This should work - chainableFoo() returns Foo per docblock
}
