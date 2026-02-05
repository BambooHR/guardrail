<?php declare(strict_types=1);

const TEST_CONST = 2 | 1 | 256 | 512;
const TEST_AND = 255 & 127 & 63;
const TEST_XOR = 15 ^ 7 ^ 3;
const TEST_SHIFT_LEFT = 1 << 2 << 3;
const TEST_SHIFT_RIGHT = 1024 >> 2 >> 1;

function testFunc(int $a) : int {
	return 1;
}

testFunc(1);
testFunc(TEST_CONST);
testFunc(TEST_AND);
testFunc(TEST_XOR);
testFunc(TEST_SHIFT_LEFT);
testFunc(TEST_SHIFT_RIGHT);

class TestClass {
	const TEST_CONST = 2 | 1 | 256 | 512;
	const TEST_AND = 255 & 127 & 63;
	const TEST_XOR = 15 ^ 7 ^ 3;
	const TEST_SHIFT_LEFT = 1 << 2 << 3;
	const TEST_SHIFT_RIGHT = 1024 >> 2 >> 1;

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
$test->testFunc(1);
