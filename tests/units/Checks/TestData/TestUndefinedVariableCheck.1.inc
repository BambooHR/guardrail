<?php

class TestClass {
	function getYearMonthDay(&$year, &$month, &$day, $randomz) {
		return;
	}
	function testForClass1($var, TestClass2 $class2) {
		preg_match('/(\d{3})(\d{3})(\d{4})/', $var, $matches);
		if (0 === sizeof($matches)) {
			return '';
		}

		// $random should fail the defined variable check because that one is not passed by reference
		$test = $this->getYearMonthDay($originalYear, $originalMonth, $originalDay, $random);
		// test when newing up variable
		$test = (new TestClass2)->testForClass2($one, $two, $three);

		$class2->testForClass2($eight, $nine, $ten);
		$varByReference = &$matches;

		return "({$matches[1]}) {$matches[2]}-{$matches[3]}";
	}


}

class TestClass2 {
	function testForClass2(&$one, &$two, &$three) {

	}
}