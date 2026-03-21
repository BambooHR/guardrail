<?php

namespace BambooHR\Guardrail\Tests;

/**
 * Comprehensive test suite for flow-sensitive type narrowing
 * 
 * Tests all scenarios where variables should be narrowed or marked as mayBeNull/mayBeUnset
 */

class FlowSensitiveTypeNarrowingTest {
	
	// ========================================
	// TEST 1: Basic If Statement Narrowing
	// ========================================
	
	function testIfNullCheck($x) {
		// $x may be null here
		$x->method(); // ERROR: mayBeNull
		
		if ($x !== null) {
			$x->method(); // OK: narrowed to non-null
		}
		
		// $x may be null again (branches merged)
		$x->method(); // ERROR: mayBeNull
	}
	
	function testIfEarlyReturn($x) {
		// $x may be null here
		$x->method(); // ERROR: mayBeNull
		
		if ($x === null) {
			return;
		}
		
		// $x is NOT null here (inverse narrowing from early exit)
		$x->method(); // OK: narrowed to non-null
	}
	
	function testIfIsset($x) {
		if (isset($x)) {
			$x->method(); // OK: isset proves non-null and set
		}
		
		// $x may be null or unset here
		$x->method(); // ERROR: mayBeNull
	}
	
	function testIfInstanceOf($x) {
		if ($x instanceof \stdClass) {
			$x->method(); // OK: instanceof proves non-null and type
		}
		
		// $x may be null here
		$x->method(); // ERROR: mayBeNull
	}
	
	function testIfTruthyCheck($x) {
		if ($x) {
			$x->method(); // OK: truthy check proves non-null
		}
		
		// $x may be null here
		$x->method(); // ERROR: mayBeNull
	}
	
	// ========================================
	// TEST 2: If/Else Branch Merging
	// ========================================
	
	function testIfElseNewVariable($condition) {
		if ($condition) {
			$x = "string";
		} else {
			$x = 123;
		}
		
		// $x is set (exists in both branches)
		echo $x; // OK: type is string|int, mayBeUnset = false
	}
	
	function testIfWithoutElseNewVariable($condition) {
		if ($condition) {
			$x = "string";
		}
		
		// $x may not be set (only exists in one branch)
		echo $x; // ERROR: mayBeUnset
	}
	
	function testIfElseIfElseNewVariable($condition) {
		if ($condition === 1) {
			$x = "one";
		} elseif ($condition === 2) {
			$x = "two";
		} else {
			$x = "other";
		}
		
		// $x is set (exists in all branches)
		echo $x; // OK: type is string, mayBeUnset = false
	}
	
	function testIfElseIfWithoutElseNewVariable($condition) {
		if ($condition === 1) {
			$x = "one";
		} elseif ($condition === 2) {
			$x = "two";
		}
		
		// $x may not be set (no else branch)
		echo $x; // ERROR: mayBeUnset
	}
	
	function testIfElseNullability($condition, $y) {
		if ($condition) {
			$x = $y; // $y may be null
		} else {
			$x = "not null";
		}
		
		// $x may be null (one branch assigns potentially null value)
		$x->method(); // ERROR: mayBeNull
	}
	
	function testIfElseBothExit($condition) {
		if ($condition) {
			return "a";
		} else {
			return "b";
		}
		
		// This code is unreachable
		$x = "never set";
	}
	
	// ========================================
	// TEST 3: Switch Statement Tests
	// ========================================
	
	function testSwitchWithDefault($value) {
		switch ($value) {
			case 1:
				$x = "one";
				break;
			case 2:
				$x = "two";
				break;
			default:
				$x = "other";
				break;
		}
		
		// $x is set (all cases including default)
		echo $x; // OK: type is string, mayBeUnset = false
	}
	
	function testSwitchWithoutDefault($value) {
		switch ($value) {
			case 1:
				$x = "one";
				break;
			case 2:
				$x = "two";
				break;
		}
		
		// $x may not be set (no default case)
		echo $x; // ERROR: mayBeUnset
	}
	
	function testSwitchFallThrough($value) {
		switch ($value) {
			case 1:
				$x = "one";
				// No break - falls through
			case 2:
				$x .= " or two"; // $x might be undefined here!
				break;
			default:
				$x = "other";
				break;
		}
		
		// $x is set but may have been used undefined in case 2
		echo $x; // OK: set in all paths, but case 2 has issue
	}
	
	function testSwitchEarlyExit($value) {
		switch ($value) {
			case 1:
				return "one";
			case 2:
				return "two";
			default:
				return "other";
		}
		
		// Unreachable
		$x = "never";
	}
	
	function testSwitchMixedExits($value) {
		switch ($value) {
			case 1:
				$x = "one";
				return;
			case 2:
				$x = "two";
				break;
			default:
				$x = "other";
				break;
		}
		
		// $x is set (case 1 exits, but cases 2 and default set it)
		echo $x; // OK: mayBeUnset = false
	}
	
	// ========================================
	// TEST 4: Loop Tests
	// ========================================
	
	function testWhileLoop($stmt) {
		while ($row = $stmt->fetch()) {
			// $row is non-null inside loop (truthy check)
			$row->process(); // OK: narrowed to non-null
		}
		
		// $row is null/false after loop (inverse narrowing)
		$row->process(); // ERROR: mayBeNull
	}
	
	function testWhileWithNullCheck($items) {
		$item = null;
		while ($item = array_shift($items)) {
			// $item is non-null inside loop
			$item->process(); // OK
		}
		
		// $item is null/false after loop
		$item->process(); // ERROR: mayBeNull
	}
	
	function testDoWhileLoop($stmt) {
		do {
			$row = $stmt->fetch();
			// $row may be null here
			$row->process(); // ERROR: mayBeNull
		} while ($row !== null);
		
		// $row is null after loop (inverse of condition)
		$row->process(); // ERROR: mayBeNull
	}
	
	// ========================================
	// TEST 5: Try/Catch/Finally Tests
	// ========================================
	
	function testTryNewVariable() {
		try {
			$x = getValue(); // May throw before this
			$y = process($x); // May throw before this
		} catch (\Exception $e) {
			// $x and $y don't exist here
		}
		
		// $x and $y may not be set (try may have failed early)
		echo $x; // ERROR: mayBeUnset
		echo $y; // ERROR: mayBeUnset
	}
	
	function testTryCatchNewVariables() {
		try {
			$x = "try";
		} catch (\Exception $e) {
			$y = "catch";
		}
		
		// Both may not be set
		echo $x; // ERROR: mayBeUnset (only set if no exception)
		echo $y; // ERROR: mayBeUnset (only set if exception)
	}
	
	function testTryFinallyNewVariable() {
		try {
			$x = "try"; // May throw before this
		} finally {
			$y = "finally"; // Always executes
		}
		
		// $x may not be set, but $y is guaranteed
		echo $x; // ERROR: mayBeUnset
		echo $y; // OK: finally always runs
	}
	
	function testTryCatchFinallyNewVariables() {
		try {
			$x = "try";
		} catch (\Exception $e) {
			$y = "catch";
		} finally {
			$z = "finally";
		}
		
		// $x and $y may not be set, but $z is guaranteed
		echo $x; // ERROR: mayBeUnset
		echo $y; // ERROR: mayBeUnset
		echo $z; // OK: finally always runs
	}
	
	function testCatchExceptionVariable() {
		try {
			throw new \Exception("test");
		} catch (\Exception $e) {
			// $e is set and non-null
			echo $e->getMessage(); // OK
		}
		
		// $e doesn't exist here
		echo $e->getMessage(); // ERROR: undefined variable
	}
	
	// ========================================
	// TEST 6: Short-Circuit Evaluation Tests
	// ========================================
	
	function testAndShortCircuit($x) {
		// $x may be null
		if ($x !== null && $x->isValid()) {
			// Both conditions true
			$x->process(); // OK: $x is non-null
		}
	}
	
	function testAndShortCircuitAssignment() {
		if (($x = getValue()) && $x->isValid()) {
			// $x is assigned and non-null (truthy)
			$x->process(); // OK
		}
		
		// $x may be null or unset here
		$x->process(); // ERROR: mayBeNull or mayBeUnset
	}
	
	function testOrShortCircuit($x) {
		// $x may be null
		if ($x === null || $x->isValid()) {
			// Either $x is null OR it's valid
			// Can't narrow here
		}
	}
	
	function testOrShortCircuitWithExit($x) {
		if ($x === null || $x->isEmpty()) {
			return;
		}
		
		// $x is non-null and not empty
		$x->process(); // OK: both conditions failed
	}
	
	function testComplexShortCircuit($x, $y) {
		if ($x !== null && $y !== null && $x->equals($y)) {
			// All three conditions true
			$x->process(); // OK: $x is non-null
			$y->process(); // OK: $y is non-null
		}
	}
	
	// ========================================
	// TEST 7: Ternary Operator Tests
	// ========================================
	
	function testTernaryNarrowing($x) {
		$result = $x !== null ? $x->getValue() : "default";
		
		// $x may still be null here
		$x->process(); // ERROR: mayBeNull
	}
	
	function testTernaryAssignment($x) {
		$y = $x !== null ? $x : new \stdClass();
		
		// $y is guaranteed non-null
		$y->process(); // OK: either $x (non-null) or new object
	}
	
	// ========================================
	// TEST 8: Type Check Function Tests
	// ========================================
	
	function testIsNull($x) {
		if (is_null($x)) {
			// $x is null
			$x->method(); // ERROR: definitely null
		} else {
			// $x is not null
			$x->method(); // OK: narrowed to non-null
		}
	}
	
	function testIsString($x) {
		if (is_string($x)) {
			// $x is string and non-null
			$x->method(); // ERROR: strings don't have methods (different error)
			strlen($x); // OK: $x is string
		}
	}
	
	function testIsObject($x) {
		if (is_object($x)) {
			// $x is object and non-null
			$x->method(); // OK: objects can have methods
		}
	}
	
	// ========================================
	// TEST 9: Negation Tests
	// ========================================
	
	function testNotNull($x) {
		if (!($x === null)) {
			// $x is not null
			$x->method(); // OK: narrowed to non-null
		}
	}
	
	function testNotIsNull($x) {
		if (!is_null($x)) {
			// $x is not null
			$x->method(); // OK: narrowed to non-null
		}
	}
	
	function testDoubleNegation($x) {
		if (!!$x) {
			// $x is truthy (non-null)
			$x->method(); // OK: narrowed to non-null
		}
	}
	
	// ========================================
	// TEST 10: Complex Nested Scenarios
	// ========================================
	
	function testNestedIfStatements($x, $y) {
		if ($x !== null) {
			if ($y !== null) {
				// Both non-null
				$x->method(); // OK
				$y->method(); // OK
			}
			// $x still non-null, $y may be null
			$x->method(); // OK
			$y->method(); // ERROR: mayBeNull
		}
		// Both may be null
		$x->method(); // ERROR: mayBeNull
		$y->method(); // ERROR: mayBeNull
	}
	
	function testIfInLoop($items) {
		foreach ($items as $item) {
			if ($item !== null) {
				$item->process(); // OK: narrowed to non-null
			}
		}
	}
	
	function testLoopInIf($condition, $items) {
		if ($condition) {
			foreach ($items as $item) {
				$x = $item;
			}
		}
		
		// $x may not be set (only set if condition true and items not empty)
		echo $x; // ERROR: mayBeUnset
	}
	
	function testTryInIf($condition) {
		if ($condition) {
			try {
				$x = getValue();
			} catch (\Exception $e) {
				$x = "error";
			}
		}
		
		// $x may not be set (only set if condition true)
		echo $x; // ERROR: mayBeUnset
	}
	
	function testIfInTry() {
		try {
			if (someCondition()) {
				$x = "value";
			}
		} catch (\Exception $e) {
			// $x doesn't exist here
		}
		
		// $x may not be set (try may have failed, or condition false)
		echo $x; // ERROR: mayBeUnset
	}
	
	function testSwitchInTry($value) {
		try {
			switch ($value) {
				case 1:
					$x = "one";
					break;
				default:
					$x = "other";
					break;
			}
		} catch (\Exception $e) {
			// Exception
		}
		
		// $x may not be set (try may have failed)
		echo $x; // ERROR: mayBeUnset
	}
	
	// ========================================
	// TEST 11: Edge Cases
	// ========================================
	
	function testEmptyIf($x) {
		if ($x !== null) {
			// Empty block
		}
		
		// $x may be null (no else, so branches merge)
		$x->method(); // ERROR: mayBeNull
	}
	
	function testMultipleReturns($x) {
		if ($x === null) {
			return;
		}
		
		if ($x->isEmpty()) {
			return;
		}
		
		// $x is non-null and not empty
		$x->process(); // OK: both early exits eliminated null and empty
	}
	
	function testAssignmentInCondition() {
		if ($x = getValue()) {
			// $x is truthy (non-null)
			$x->method(); // OK
		}
		
		// $x may be null/false
		$x->method(); // ERROR: mayBeNull
	}
	
	function testVariableVariables($name) {
		$x = "value";
		$$name = "dynamic"; // Can't track this
		
		// $x should still be known
		echo $x; // OK: $x is set
	}
	
	// ========================================
	// TEST: Match Expression Type Narrowing
	// ========================================
	
	function testMatchAllNonNull($format) {
		// All arms return non-null objects, including default
		$output = match ($format) {
			'text'   => new \stdClass(),
			'counts' => new \ArrayObject(),
			'csv'    => new \DateTime(),
			default  => new \Exception()
		};
		
		// $output should be non-null (union of all return types)
		$output->method(); // OK: all match arms return non-null
	}
	
	function testMatchWithNullableArm($format) {
		// One arm returns null
		$output = match ($format) {
			'text'   => new \stdClass(),
			'counts' => null,
			default  => new \Exception()
		};
		
		// $output may be null
		$output->method(); // ERROR: mayBeNull (one arm returns null)
	}
	
	function testMatchNoDefault($value) {
		// No default arm - could throw UnhandledMatchError
		$result = match ($value) {
			1 => 'one',
			2 => 'two',
			3 => 'three'
		};
		
		// $result should be string (all arms return string)
		// Note: No default means runtime error is possible, but type is still string
		echo strlen($result); // OK: type is string
	}
	
	function testMatchMixedTypes($value) {
		// Different types in different arms
		$result = match ($value) {
			'int'    => 42,
			'string' => 'hello',
			'bool'   => true,
			default  => null
		};
		
		// $result is int|string|bool|null
		$result->method(); // ERROR: mayBeNull and not all types have method()
	}
	
	function testMatchInAssignment($config) {
		// Real-world example: match result assigned to variable
		$output = match ($config->getFormat()) {
			'text'   => new \stdClass(),
			'counts' => new \ArrayObject(),
			default  => new \DateTime()
		};
		
		// Use $output later - should be non-null
		if ($output->someProperty) { // OK: $output is non-null
			return true;
		}
		return false;
	}
}
