<?php declare(strict_types=1);

// Bitwise constant expressions evaluate to int, the same way PHP does.
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
testFunc(TEST_CONST);             // int (2 | 1 | 256 | 512)
testFunc(TEST_AND);               // int (255 & 127 & 63)
testFunc(TEST_XOR);               // int (15 ^ 7 ^ 3)
testFunc(TEST_SHIFT_LEFT);        // int (1 << 2 << 3)
testFunc(TEST_SHIFT_RIGHT);       // int (1024 >> 2 >> 1)
testFunc(TEST_CONST_FROM_CONSTS); // int (E_WARNING | E_ERROR | E_USER_ERROR | E_USER_WARNING)
