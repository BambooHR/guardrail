<?php

// Test that runtime PHP constants are correctly typed

function testIntConstants(int $value) {
	return $value > 0;
}

function testStringConstants(string $value) {
	return strlen($value) > 0;
}

function testFloatConstants(float $value) {
	return $value > 0.0;
}

// These should not produce type errors
testIntConstants(FILTER_VALIDATE_EMAIL);
testIntConstants(FILTER_FLAG_NONE);
testIntConstants(PHP_INT_MAX);
testStringConstants(PHP_VERSION);
testStringConstants(PHP_OS);
testFloatConstants(PHP_FLOAT_EPSILON);

class TestClass {
	function testIntConstants(int $value) {
		return $value > 0;
	}

	function testStringConstants(string $value) {
		return strlen($value) > 0;
	}

	function testFloatConstants(float $value) {
		return $value > 0.0;
	}
}

$test = new TestClass();

// These should not produce type errors
$test->testIntConstants(FILTER_VALIDATE_EMAIL);
$test->testIntConstants(FILTER_FLAG_NONE);
$test->testIntConstants(PHP_INT_MAX);
$test->testStringConstants(PHP_VERSION);
$test->testStringConstants(PHP_OS);
$test->testFloatConstants(PHP_FLOAT_EPSILON);
