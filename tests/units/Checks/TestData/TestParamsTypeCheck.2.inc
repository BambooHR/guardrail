<?php declare(strict_types=1);

function simpleParams(int $a, float $b, string $c, bool $d, array $e, mixed $f, null $g, ?int $h, bool | int $i) : int {
	return 1;
}

function intParam(int $a) : int {
	return $a;
}

simpleParams(1, 1.0, "1", true, [1], "1", null, 1, true);
intParam(1);

const A = 1;
const B = 1.0;
const C = "1";
const D = true;
const E = [1];
const F = "1";
const G = null;
const H = 1;
const I = true;

simpleParams(A, B, C, D, E, F, G, H, I);

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

	function intParam(int $a) : int {
		return $a;
	}
}

$test = new TestClass();
$test->simpleParams(TestClass::A, TestClass::B, TestClass::C, TestClass::D, TestClass::E, TestClass::F, TestClass::G, TestClass::H, TestClass::I);
$test->intParam(TestClass::A);

intParam(TestClass::A);

class anotherClass {

	const A = 1;
	const B = TestClass::A;

    function intParam(int $a) : int {
        return $a;
    }
}

$another = new anotherClass();
$another->intParam(TestClass::A);
$another->intParam(anotherClass::B);
