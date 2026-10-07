<?php declare(strict_types=1);

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

testBoolParam("no");
testBoolParam2(1);
testNullParam(true);
testNullParam2(false);
