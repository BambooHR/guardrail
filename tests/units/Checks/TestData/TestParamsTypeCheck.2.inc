<?php declare(strict_types=1);

function simpleParams(int $a, float $b, string $c, bool $d, array $e, mixed $f) : int {
	return 1;
}

simpleParams(1, 1.0, "1", true, [1], "1");

const A = 1;
const B = 1.0;
const C = "1";
const D = true;
const E = [1];
const F = "1";

simpleParams(A, B, C, D, E, F);