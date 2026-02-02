<?php declare(strict_types=1);

function simpleParams(int $a, float $b, string $c, bool $d, array $e, mixed $f) : int {
	return 1;
}

//4 Errors
simpleParams("1", "1", true, "1", [1], "1");

const A = 1;
const B = 1.0;
const C = "1";
const D = true;
const E = [1];
const F = "1";

// 4 Errors
simpleParams(D, C, B, A, E, F);