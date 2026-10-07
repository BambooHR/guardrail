<?php declare(strict_types=1);

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

function testBoolConstants(bool $value) {
	return $value;
}

function testNullConstants(null $value): void {
	// Accepts null parameter
}

function testMixedConstants(mixed $value) {
	return $value;
}

// Int constants - should not produce type errors
testIntConstants(FILTER_VALIDATE_EMAIL);
testIntConstants(FILTER_VALIDATE_INT);
testIntConstants(PHP_INT_MAX);
testIntConstants(E_ERROR);
testIntConstants(E_WARNING);
testIntConstants(JSON_PRETTY_PRINT);
testIntConstants(PREG_OFFSET_CAPTURE);
testIntConstants(ENT_QUOTES);
testIntConstants(STR_PAD_LEFT);

// String constants - should not produce type errors
testStringConstants(PHP_VERSION);
testStringConstants(PHP_OS);

// Float constants - should not produce type errors
testFloatConstants(PHP_FLOAT_EPSILON);
testFloatConstants(M_PI);
testFloatConstants(M_E);

// Bool constants - should not produce type errors
testBoolConstants(TRUE);
testBoolConstants(FALSE);

// Null constants - should not produce type errors
testNullConstants(NULL);

// Mixed constants - should not produce type errors (mixed accepts anything)
testMixedConstants(PHP_VERSION);
testMixedConstants(TRUE);
testMixedConstants(NULL);
