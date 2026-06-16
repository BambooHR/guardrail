<?php declare(strict_types=1);

const standaloneInt = 1;
define("definedInt", 1);

class TestClass {
	const A = 1;
	private const int ATypedPrivate = 1;
	public const int ATypedPublic = 1;
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

	function privateIntTest() : int {
		return intTest(TestClass::ATypedPrivate);
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

}

$test = new TestClass();
$test->intTest(TestClass::ATypedPublic);
$test->intTest(TestClass::A);
$test->privateIntTest();
$test->intTest(standaloneInt);
$test->intTest(definedInt);
$test->floatTest(TestClass::B);
$test->stringTest(TestClass::C);
$test->boolTest(TestClass::D);
$test->arrayTest(TestClass::E);
$test->mixedTest(TestClass::F);
$test->nullTest(TestClass::G);
$test->nullableInt(TestClass::H);
$test->boolOrInt(TestClass::I);


class anotherClass {

	const A = 1;
	const B = TestClass::A;

    function intTestOtherClass(int $a) : int {
        return $a;
    }
}

$another = new anotherClass();
$another->intTestOtherClass(TestClass::A);
$another->intTestOtherClass(anotherClass::A);
$another->intTestOtherClass(anotherClass::B);

function testFunction(int $a) : int {
    return $a;
}

testFunction(1);
testFunction(TestClass::A);
testFunction(anotherClass::A);
testFunction(anotherClass::B);
