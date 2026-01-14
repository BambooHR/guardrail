<?php

function testFunction(): int {
	return 42;
}

class TestClass {
	function method() {
		$closure = function(): string {
			return "test";
		};

		$arrow = fn(): bool => true;

		return $closure();
	}
}
