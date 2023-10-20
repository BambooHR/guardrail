<?php

class TestClass {
	private ?array $testArray = null;
	function method(): array {
		if (is_null($this->testArray)) {
			$this->testArray = $this->getInt();
		}
		return $this->testArray;
	}

	function method2(): array {
		if (!$this->testArray) {
			$this->testArray = $this->getInt();
		}
		return $this->testArray;
	}

	function getInt(): array {
		return [];
	}
}