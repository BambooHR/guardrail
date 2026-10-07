<?php declare(strict_types=0);

// User-defined constants are typed as mixed, including ones defined with
// bitwise expressions. With strict_types=0, a mixed argument is compatible
// with any parameter, so these calls produce no signature errors.
// testFunc(1) receives a literal int.

const TEST_CONST_FROM_CONSTS = E_WARNING | E_ERROR | E_USER_ERROR | E_USER_WARNING;
const TEST_CONST = 2 | 1 | 256 | 512;
const TEST_AND = 255 & 127 & 63;
const TEST_XOR = 15 ^ 7 ^ 3;
const TEST_SHIFT_LEFT = 1 << 2 << 3;
const TEST_SHIFT_RIGHT = 1024 >> 2 >> 1;

function testFunc(int $a) : int {
	return 1;
}

testFunc(1);                      // literal int
testFunc(TEST_CONST);             // mixed passed to int (2 | 1 | 256 | 512); allowed because strict_types=0
testFunc(TEST_AND);               // mixed passed to int (255 & 127 & 63); allowed because strict_types=0
testFunc(TEST_XOR);               // mixed passed to int (15 ^ 7 ^ 3); allowed because strict_types=0
testFunc(TEST_SHIFT_LEFT);        // mixed passed to int (1 << 2 << 3); allowed because strict_types=0
testFunc(TEST_SHIFT_RIGHT);       // mixed passed to int (1024 >> 2 >> 1); allowed because strict_types=0
testFunc(TEST_CONST_FROM_CONSTS); // mixed passed to int (E_WARNING | E_ERROR | E_USER_ERROR | E_USER_WARNING); allowed because strict_types=0
