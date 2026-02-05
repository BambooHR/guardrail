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

// Test in conditionals
if (true) {
	$x = 1;
}

if (false) {
	$y = 2;
}

$z = null;
