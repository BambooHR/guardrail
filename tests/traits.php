<?php

namespace SomeNameSpace;

trait MyTrait {
	function traitFoo() {
		echo "Hello world!\n";
	}
	function traitFoo2() {

	}
}

class MyTraitTest {
	use MyTrait;

	function callThisTrait() {
		$this->traitFoo(); // Ok.
		$this->bar();      // Not ok
	}
}

class DescendantTest extends MyTraitTest {
	function callParentTrait() {
		$this->traitFoo(); // Ok
		$this->traitFoo2();// Ok
		$this->bar();      // Not ok
	}
}

function wrapper() {
	$myTraitTestInstance=new MyTraitTest();
	$myTraitTestInstance->traitFoo(); // Ok
	$myTraitTestInstance->bar();      // Not ok
}

