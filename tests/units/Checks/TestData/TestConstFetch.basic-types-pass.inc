<?php declare(strict_types=1);

// User constants are typed from their values, the same way PHP evaluates them.
// nullTest(null) and nullTest(G) both receive null.

const A = 1;
const B = 1.0;
const C = "1";
const D = true;
const E = [1];
const F = "1";
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

function mixedTest(mixed $f) : mixed {
	return $f;
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

intTest(A);     // int (A = 1)
floatTest(B);   // float (B = 1.0)
stringTest(C);  // string (C = "1")
boolTest(D);    // true (D = true)
arrayTest(E);   // array (E = [1])
mixedTest(F);   // string (F = "1")
nullTest(null); // literal null
nullTest(G);    // null (G = null)
nullableInt(H); // int (H = 1)
boolOrInt(I);   // true (I = true)
