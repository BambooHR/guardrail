<?php declare(strict_types=1);

class InvalidReturns {
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

    function returnsIntFromString(): int {
        return "123";
    }

    function returnsFloatFromString(): float {
        return "3.14";
    }

    function returnsIntFromFloat(): int {
        return 3.14;
    }

    function returnsIntFromBool(): int {
        return true;
    }

    function returnsStringFromBool(): string {
        return false;
    }

    function returnsStringFromInt(): string {
        return 123;
    }

    function returnsStringFromFloat(): string {
        return 3.14;
    }

    function returnsBoolFromInt(): bool {
        return 1;
    }

    function returnsBoolFromZero(): bool {
        return 0;
    }

    function returnsBoolFromString(): bool {
        return "true";
    }
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

function globalReturnsIntFromString(): int {
    return "123";
}

function globalReturnsFloatFromString(): float {
    return "3.14";
}

function globalReturnsIntFromFloat(): int {
    return 3.14;
}

function globalReturnsIntFromBool(): int {
    return true;
}

function globalReturnsStringFromBool(): string {
    return false;
}

function globalReturnsStringFromInt(): string {
    return 123;
}

function globalReturnsStringFromFloat(): string {
    return 3.14;
}

function globalReturnsBoolFromInt(): bool {
    return 1;
}

function globalReturnsBoolFromZero(): bool {
    return 0;
}

function globalReturnsBoolFromString(): bool {
    return "true";
}
