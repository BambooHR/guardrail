<?php declare(strict_types=1);

function simpleParams(int $a, float $b, string $c, bool $d, array $e, null $f) : int {
	return 1;
}

simpleParams(1, 1.0, "1", true, [1], null);
