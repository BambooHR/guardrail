<?php

// Self return type
class SelfReturnType {
	function method(): self {
		return new self();
	}
}

// Standalone function, closure, arrow function
function testFunction(): int {
	return 42;
}

class ClosuresAndArrows {
	function method() {
		$closure = function(): string {
			return "test";
		};

		$arrow = fn(): bool => true;

		return $closure();
	}
}
