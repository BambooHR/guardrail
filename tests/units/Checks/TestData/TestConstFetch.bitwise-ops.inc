<?php declare(strict_types=1);

const TEST_CONST_FROM_CONSTS = E_WARNING | E_ERROR | E_USER_ERROR | E_USER_WARNING;
const TEST_CONST = 2 | 1 | 256 | 512;
const TEST_AND = 255 & 127 & 63;
const TEST_XOR = 15 ^ 7 ^ 3;
const TEST_SHIFT_LEFT = 1 << 2 << 3;
const TEST_SHIFT_RIGHT = 1024 >> 2 >> 1;

function testFunc(int $a) : int {
	return 1;
}

testFunc(1);
testFunc(TEST_CONST);
testFunc(TEST_AND);
testFunc(TEST_XOR);
testFunc(TEST_SHIFT_LEFT);
testFunc(TEST_SHIFT_RIGHT);
testFunc(TEST_CONST_FROM_CONSTS);
