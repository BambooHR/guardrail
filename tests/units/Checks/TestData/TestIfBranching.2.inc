<?php

class TestObject {
	public string $test;
}

class TestClass {
	private ?TestObject $testObj = null;

	function method(?TestObject $testObj): string {
		if (is_null($this->testObj)) {
			return '';
		}
		if (!$testObj) {
			return '';
		}
		$this->testObj->test = $testObj->test;
		return $this->testObj->test;
	}
}