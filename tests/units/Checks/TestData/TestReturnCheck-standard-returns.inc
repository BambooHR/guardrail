<?php

// Valid return types - all should pass
class ValidReturns {
	function intReturn(): int {
		return 42;
	}

	function stringReturn(): string {
		return "test";
	}

	function arrayReturn(): array {
		return [1, 2, 3];
	}

	function boolReturn(): bool {
		return true;
	}

	function floatReturn(): float {
		return 3.14;
	}

	function objectReturn(): object {
		return new \stdClass();
	}

	function callableReturn(): callable {
		return function() {};
	}

	function nullableReturn(): ?int {
		return null;
	}

	function nullableReturnInt(): ?int {
		return 42;
	}

	function unionReturn(): int|string {
		return 42;
	}

	function unionReturnString(): int|string {
		return "test";
	}
}


function intReturn(): int {
	return 42;
}

function stringReturn(): string {
	return "test";
}

function arrayReturn(): array {
	return [1, 2, 3];
}

function boolReturn(): bool {
	return true;
}

function floatReturn(): float {
	return 3.14;
}

function objectReturn(): object {
	return new \stdClass();
}

function callableReturn(): callable {
	return function() {};
}

function nullableReturn(): ?int {
	return null;
}

function nullableReturnInt(): ?int {
	return 42;
}

function unionReturn(): int|string {
	return 42;
}

function unionReturnString(): int|string {
	return "test";
}