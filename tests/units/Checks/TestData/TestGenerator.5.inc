<?php

class TestClass {
	function method(): int {
		// Should fail because it is an empty function, which returns `int` and not a generator
	}
	function method(): string {
		// Should fail because it is an empty function, which returns `int` and not a generator
	}
	function method(): array {
		// Should fail because it is an empty function, which returns `int` and not a generator
	}
	function method(): bool {
		// Should fail because it is an empty function, which returns `int` and not a generator
	}
	function method(): callable {
		// Should fail because it is an empty function, which returns `int` and not a generator
	}
	function method(): object {
		// Should fail because it is an empty function, which returns `int` and not a generator
	}
	function method(): self {
		// Should fail because it is an empty function, which returns `int` and not a generator
	}
	function method(): static {
		// Should fail because it is an empty function, which returns `int` and not a generator
	}
}