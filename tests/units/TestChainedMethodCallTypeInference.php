<?php

namespace BambooHR\Guardrail\Tests\Units;

/**
 * Low-level tests for chained method call type inference with docblock return types
 * 
 * This tests the scenario where:
 * 1. A method returns a type specified in a docblock (not native return type)
 * 2. The result is assigned to a variable
 * 3. That variable is used to call another method
 */

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

class Baz {
	/**
	 * @return Baz
	 */
	function chainableBaz() {
		return $this;
	}
}

// ========================================
// TEST 1: Basic chained method call with assignment
// ========================================

function testBasicChainedMethodCall() {
	// This should work: chainableFoo() returns Foo per docblock
	$var = (new Foo())->chainableFoo();
	$var->chainableFoo(); // OK: $var should be inferred as Foo
	$var->doSomething(); // OK: $var is Foo
}

// ========================================
// TEST 2: Multiple chained calls before assignment
// ========================================

function testMultipleChainedCalls() {
	// Multiple chains before assignment
	$var = (new Foo())->chainableFoo()->chainableFoo();
	$var->doSomething(); // OK: $var should be Foo
}

// ========================================
// TEST 3: Cross-type chained calls
// ========================================

function testCrossTypeChainedCalls() {
	// Method returns different type
	$bar = (new Foo())->getBar();
	$bar->chainableBar(); // OK: $bar should be Bar
	$bar->process(); // OK: $bar is Bar
}

// ========================================
// TEST 4: Longer chains with type changes
// ========================================

function testLongerChains() {
	$result = (new Foo())->getBar()->chainableBar();
	$result->process(); // OK: $result should be Bar
}

// ========================================
// TEST 5: Reassignment with chained calls
// ========================================

function testReassignment() {
	$var = new Foo();
	$var = $var->chainableFoo(); // Reassign with method call result
	$var->doSomething(); // OK: $var should still be Foo
}

// ========================================
// TEST 6: Chained calls without assignment
// ========================================

function testDirectChainedCalls() {
	// Direct chaining without intermediate variable
	(new Foo())->chainableFoo()->doSomething(); // OK: all should work
}

// ========================================
// TEST 7: Property assignment with chained calls
// ========================================

class Container {
	public $foo;
	
	function testPropertyAssignment() {
		$this->foo = (new Foo())->chainableFoo();
		$this->foo->doSomething(); // OK: $this->foo should be Foo
	}
}

// ========================================
// TEST 8: Conditional assignment with chained calls
// ========================================

function testConditionalAssignment($condition) {
	if ($condition) {
		$var = (new Foo())->chainableFoo();
	} else {
		$var = (new Baz())->chainableBaz();
	}
	
	// $var could be Foo|Baz
	$var->chainableFoo(); // ERROR: Baz doesn't have chainableFoo
}

// ========================================
// TEST 9: Array of chained call results
// ========================================

function testArrayOfResults() {
	$items = [
		(new Foo())->chainableFoo(),
		(new Foo())->getBar(),
	];
	
	// $items[0] should be Foo
	// $items[1] should be Bar
	// But array type inference might not track individual elements
}

// ========================================
// TEST 10: Null safety with chained calls
// ========================================

function testNullSafety($maybeFoo) {
	// $maybeFoo might be null
	$var = $maybeFoo?->chainableFoo(); // Nullsafe operator
	$var->doSomething(); // ERROR: $var may be null
}

// ========================================
// TEST 11: Method with no docblock return type
// ========================================

class NoDocblock {
	function getResult() {
		return new Foo();
	}
}

function testNoDocblock() {
	$var = (new NoDocblock())->getResult();
	$var->doSomething(); // Might not work if no type inference
}

// ========================================
// TEST 12: Static method chained calls
// ========================================

class StaticMethods {
	/**
	 * @return Foo
	 */
	static function createFoo() {
		return new Foo();
	}
}

function testStaticMethodChain() {
	$var = StaticMethods::createFoo()->chainableFoo();
	$var->doSomething(); // OK: $var should be Foo
}

// ========================================
// TEST 13: Chained calls in function arguments
// ========================================

function processFoo(Foo $foo) {
	return $foo->doSomething();
}

function testChainedCallAsArgument() {
	processFoo((new Foo())->chainableFoo()); // OK: should infer Foo
}

// ========================================
// TEST 14: Return chained call result
// ========================================

/**
 * @return Foo
 */
function getChainedFoo() {
	return (new Foo())->chainableFoo(); // OK: matches return type
}

function testReturnChainedCall() {
	$var = getChainedFoo();
	$var->doSomething(); // OK: $var is Foo from function return type
}

// ========================================
// TEST 15: Complex nested chains
// ========================================

class Complex {
	/**
	 * @return Foo
	 */
	function getFoo() {
		return new Foo();
	}
}

function testComplexNesting() {
	$var = (new Complex())->getFoo()->getBar()->chainableBar();
	$var->process(); // OK: $var should be Bar
}
