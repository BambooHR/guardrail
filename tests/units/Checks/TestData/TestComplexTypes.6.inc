<?php

class TestClass {

	private $testArray;

	function method(): array {
		$this->testArray->foo = true ? 'bar' : [];
		if (!is_array($this->testArray->foo)) {
			$this->testArray->foo = explode(',', $this->testArray->foo);
		}
		return $this->testArray;
	}
}
