<?php declare(strict_types=1);

const standaloneInt = 1;
define("definedInt", 1);

class TestClass {
	function intTest(int $a) : int {
		return $a;
	}
}

$test = new TestClass();
$test->intTest(standaloneInt);
$test->intTest(definedInt);
