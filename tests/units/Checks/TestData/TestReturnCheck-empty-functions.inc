<?php

// Empty functions that should PASS (no return required)
class EmptyFunctionsPass {
	function noneReturn(): none {}

	function voidReturn(): void {}

	function mixedReturn(): mixed {}
}

// Empty functions that should FAIL (return required)
class EmptyFunctionsFail {
	function intReturn(): int {}

	function stringReturn(): string {}

	function arrayReturn(): array {}

	function boolReturn(): bool {}

	function callableReturn(): callable {}

	function objectReturn(): object {}

	function selfReturn(): self {}

	function staticReturn(): static {}
}

// Abstract method - should pass
abstract class AbstractMethods {
	abstract function method(): int;
}
