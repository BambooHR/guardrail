<?php declare(strict_types=1);

// User-defined constants are typed as mixed. strict_types does not reject
// mixed, so these calls produce no signature errors. nullTest(null) receives
// the literal null, which is typed as null. nullTest(G) receives a null
// constant, which is typed as mixed.

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

intTest(A);     // mixed passed to int (A = 1)
floatTest(B);   // mixed passed to float (B = 1.0)
stringTest(C);  // mixed passed to string (C = "1")
boolTest(D);    // mixed passed to bool (D = true)
arrayTest(E);   // mixed passed to array (E = [1])
mixedTest(F);   // mixed passed to mixed (F = "1")
nullTest(null); // literal null, typed as null
nullTest(G);    // mixed passed to null (G = null)
nullableInt(H); // mixed passed to ?int (H = 1)
boolOrInt(I);   // mixed passed to bool|int (I = true)
