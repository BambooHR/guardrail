<?php

// Test that true, false, and null constants are correctly typed

function testBoolParam(bool $value) {
	return $value;
}

function testNullParam(?string $value) {
	return $value;
}

// These should not produce type errors
testBoolParam(true);
testBoolParam(false);
testNullParam(null);

class TestClass {
	function testBoolParam(bool $value) {
		return $value;
	}

	function testNullParam(?string $value) {
		return $value;
	}
}

$test = new TestClass();

// These should not produce type errors
testBoolParam($test->testBoolParam(true));
testBoolParam($test->testBoolParam(false));
testNullParam($test->testNullParam(null));
