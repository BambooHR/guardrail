<?php declare(strict_types=1);

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

$test->intTest(A);
$test->floatTest(B);
$test->stringTest(C);
$test->boolTest(D);
$test->arrayTest(E);
$test->mixedTest(F);
$test->nullTest(G);
$test->nullableInt(H);
$test->boolOrInt(I);
