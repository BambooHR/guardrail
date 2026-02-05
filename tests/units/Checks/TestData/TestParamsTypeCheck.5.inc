<?php declare(strict_types=1);

define('A', 1);
define('B', 1.0);
define('C', "1");
define('D', true);
define('E', [1]);

function simpleParams(int $a, float $b, string $c, bool $d, array $e) : int {
	return 1;
}

// 5 Errors
simpleParams(true, "1", 1.0, [1], 1);

// 5 Errors
simpleParams(D, C, B, E, A);
