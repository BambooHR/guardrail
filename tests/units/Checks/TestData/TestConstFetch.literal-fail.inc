<?php declare(strict_types=1);

// User constants are typed from their values. Each call passes a constant to
// a parameter of a different scalar type.

const STRING_CONST = "1";
const INT_CONST = 1;
const FLOAT_CONST = 1.5;
const SHIFTED = 1 << 2;

function intTest(int $value): void {
}

function stringTest(string $value): void {
}

intTest(STRING_CONST);   // string passed to int
stringTest(INT_CONST);   // int passed to string
intTest(FLOAT_CONST);    // float passed to int
stringTest(SHIFTED);     // int passed to string (1 << 2)
