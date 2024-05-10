<?php

class TestClass {

	private string|array $testArray = "foo";

	function method(): array {
		if (!is_array($this->testArray)) {
			$this->testArray = explode(',', $this->testArray);
		}
		return $this->testArray;
	}
}
