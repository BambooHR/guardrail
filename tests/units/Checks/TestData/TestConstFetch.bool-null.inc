<?php

// Test that true, false, and null constants are correctly typed

function testBoolParam(bool $value) {
	return $value;
}

function testBoolParam2(?bool $value) {
	return $value;
}

function testNullParam(?string $value) {
	return $value;
}

function testNullParam2(null $value) {
	return $value;
}

// These should not produce type errors
testBoolParam(true);
testBoolParam(false);
testBoolParam2(true);
testBoolParam2(false);
testBoolParam2(null);
testNullParam(null);
testNullParam2(null);
