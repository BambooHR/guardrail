<?php

class Foo {
	/**
	 * @return Foo
	 */
	function chainableFoo() {
		return $this;
	}
	
	/**
	 * @return Bar
	 */
	function getBar() {
		return new Bar();
	}
	
	function doSomething() {
		return "done";
	}
}

class Bar {
	/**
	 * @return Bar
	 */
	function chainableBar() {
		return $this;
	}
	
	function process() {
		return "processed";
	}
}

function testBasicChainedMethodCall() {
	$var = (new Foo())->chainableFoo();
	$var->chainableFoo();
	$var->doSomething();
}

function testMultipleChainedCalls() {
	$var = (new Foo())->chainableFoo()->chainableFoo();
	$var->doSomething();
}

function testCrossTypeChainedCalls() {
	$bar = (new Foo())->getBar();
	$bar->chainableBar();
	$bar->process();
}

function testLongerChains() {
	$result = (new Foo())->getBar()->chainableBar();
	$result->process();
}

function testReassignment() {
	$var = new Foo();
	$var = $var->chainableFoo();
	$var->doSomething();
}

function testDirectChainedCalls() {
	(new Foo())->chainableFoo()->doSomething();
}
