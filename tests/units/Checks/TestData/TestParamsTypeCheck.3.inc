<?php declare(strict_types=1);

function simpleParams(int $a, float $b, string $c, bool $d, array $e, mixed $f, null $g, ?int $h, bool | int $i) : int {
	return 1;
}

//7 Errors
simpleParams("1", "1", true, "1", [1], "1", 1, "1", "1");

const A = 1;
const B = 1.0;
const C = "1";
const D = true;
const E = [1];
const F = "1";
const G = null;
const H = 1;
const I = true;

// 7 Errors
simpleParams(D, C, B, A, E, F, A, F, F);

class TestClass {
	const A = 1;
	const B = 1.0;
	const C = "1";
	const D = true;
	const E = [1];
	const F = "1";
	const G = null;
	const H = 1;
	const I = true;

	function simpleParams(int $a, float $b, string $c, bool $d, array $e, mixed $f, null $g, ?int $h, bool | int $i) : int {
		return 1;
	}
}

$test = new TestClass();
// 7 Errors
$test->simpleParams(TestClass::D, TestClass::C, TestClass::B, TestClass::A, TestClass::E, TestClass::F, TestClass::A, TestClass::F, TestClass::F);
