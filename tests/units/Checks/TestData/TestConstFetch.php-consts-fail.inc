<?php declare(strict_types=1);

// Runtime PHP constants are typed from their current value (true, false, and
// null by name). In strict mode that type must match the parameter, so each
// call below is a signature error.

function testIntConstants(int $value) {
	return $value > 0;
}

function testStringConstants(string $value) {
	return strlen($value) > 0;
}

function testFloatConstants(float $value) {
	return $value > 0.0;
}

function testBoolConstants(bool $value) {
	return $value;
}

function testNullConstants(null $value): void {
}

testIntConstants(PHP_VERSION);    // string passed to int
testIntConstants(M_PI);           // float passed to int
testStringConstants(PHP_INT_MAX); // int passed to string
testStringConstants(M_PI);        // float passed to string
testFloatConstants(PHP_VERSION);  // string passed to float
testFloatConstants(TRUE);         // true passed to float
testBoolConstants(PHP_INT_MAX);   // int passed to bool
testBoolConstants(PHP_VERSION);   // string passed to bool
testNullConstants(FALSE);         // false passed to null
testNullConstants(PHP_VERSION);   // string passed to null
