<?php declare(strict_types=1);

class testClass {
    function test(): int {
        return 1;
    }
}

// Invalid return types - all should fail
class InvalidReturns {
	function arrayReturnInt(): array {
		return 42;
	}

	function classReturnString(): testClass {
		return "not an instance of testClass";
	}

	function objectReturnString(): object {
		return "not an instance of object";
	}

	function callableReturnInt(): callable {
		return 42;
	}

	function intReturnArray(): int {
		return [];
	}

	function stringReturnArray(): string {
		return [];
	}

	function boolReturnArray(): bool {
		return [];
	}

	function floatReturnArray(): float {
		return [];
	}
	function nonNullableReturnNone(): int {
		return none;
	}

	function nonNullableReturnNull(): int {
		return null;
	}

	function nonNullableReturnNever(): int {
		return never;
	}

	function nonNullableReturnVoid(): int {
		return void;
	}

	function unionReturnString(): int|string {
		return true;
	}

	function stringReturnNoReturnEmpty(): string {}

	function stringReturnNoReturn(): string {
		$test = 1;
	}

	function stringReturnVoid(): string {
		return;
	}

	function noReturn(): string {
		$test = 1;
	}
}

// Standalone functions with invalid returns
function arrayReturnInt(): array {
	return 42;
}

function classReturnString(): testClass {
	return "not an instance of testClass";
}

function objectReturnString(): object {
	return "not an instance of object";
}

function callableReturnInt(): callable {
	return 42;
}

function intReturnArray(): int {
	return [];
}

function stringReturnArray(): string {
	return [];
}

function boolReturnArray(): bool {
	return [];
}

function floatReturnArray(): float {
	return [];
}

function nonNullableReturnNone(): int {
	return none;
}

function nonNullableReturnNull(): int {
	return null;
}

function nonNullableReturnNever(): int {
	return never;
}

function nonNullableReturnVoid(): int {
	return void;
}

function unionReturnString(): int|string {
	return true;
}

function stringReturnNoReturnEmpty(): string {}

function stringReturnNoReturn(): string {
	$test = 1;
}

function stringReturnVoid(): string {
	return;
}

function noReturn(): string {
	$test = 1;
}