<?php declare(strict_types=1);

// Constants created with define() are typed as mixed. The value passed to
// define() is never read. In strict mode, mixed is not compatible with a
// narrower parameter, so each argument below is a signature error even when
// the defined value would fit.

define('A', 1);
define('B', 1.0);
define('C', "1");
define('D', true);
define('E', [1]);
define('F', null);

function simpleParams(int $a, float $b, string $c, bool $d, array $e, null $f) : int {
	return 1;
}

simpleParams(
	A, // mixed passed to int (define value 1)
	B, // mixed passed to float (define value 1.0)
	C, // mixed passed to string (define value "1")
	D, // mixed passed to bool (define value true)
	E, // mixed passed to array (define value [1])
	F, // mixed passed to null (define value null)
);
