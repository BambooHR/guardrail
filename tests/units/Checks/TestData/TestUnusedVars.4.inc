<?php

class TestClass {
	function method() {
		$test = ['123'];
		$callback = function() use(&$test) {
			$test []= '456';
		};
		$callback();
		return $test;
	}
}