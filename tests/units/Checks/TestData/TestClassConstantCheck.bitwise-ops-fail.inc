<?php declare(strict_types=1);

class TestClass {
	const TEST_CONST_FROM_CONSTS = 1.5;
	const TEST_CONST = 2.5;
	const TEST_AND = [1];
	const TEST_XOR = 3.5;
	const TEST_SHIFT_LEFT = [2];
	const TEST_SHIFT_RIGHT = 4.5;

	function testFunc(int $a) : int {
		return 1;
	}
}

$test = new TestClass();
$test->testFunc(TestClass::TEST_CONST);
$test->testFunc(TestClass::TEST_AND);
$test->testFunc(TestClass::TEST_XOR);
$test->testFunc(TestClass::TEST_SHIFT_LEFT);
$test->testFunc(TestClass::TEST_SHIFT_RIGHT);
$test->testFunc(TestClass::TEST_CONST_FROM_CONSTS);
