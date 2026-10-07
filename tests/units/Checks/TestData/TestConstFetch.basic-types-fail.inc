<?php declare(strict_types=1);

// User-defined constants are typed as mixed. In strict mode, mixed is not
// compatible with a narrower parameter, so each call below is a signature
// error even when the constant's value would fit.

const A = 1;
const B = 1.0;
const C = "1";
const D = true;
const E = [1];
const G = null;
const H = 1;
const I = true;

function intTest(int $a) : int {
	return $a;
}

function floatTest(float $b) : float {
	return $b;
}

function stringTest(string $c) : string {
	return $c;
}

function boolTest(bool $d) : bool {
	return $d;
}

function arrayTest(array $e) : array {
	return $e;
}

function nullTest(null $g) : null {
	return $g;
}

function nullableInt(?Int $h) : ?Int {
	return $h;
}

function boolOrInt(bool | int $i) : bool | int {
	return $i;
}

intTest(A);     // mixed passed to int (A = 1)
floatTest(B);   // mixed passed to float (B = 1.0)
stringTest(C);  // mixed passed to string (C = "1")
boolTest(D);    // mixed passed to bool (D = true)
arrayTest(E);   // mixed passed to array (E = [1])
nullTest(G);    // mixed passed to null (G = null)
nullableInt(H); // mixed passed to ?int (H = 1)
boolOrInt(I);   // mixed passed to bool|int (I = true)
