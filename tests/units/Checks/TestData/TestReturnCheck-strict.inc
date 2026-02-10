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
