<?php declare(strict_types=1);

define('A', 1);
define('B', 1.0);
define('C', "1");
define('D', true);
define('E', [1]);
define('F', null);

function simpleParams(int $a, float $b, string $c, bool $d, array $e, null $f) : int {
	return 1;
}


simpleParams(1, 1.0, "1", true, [1], null);

simpleParams(A, B, C, D, E, F);
