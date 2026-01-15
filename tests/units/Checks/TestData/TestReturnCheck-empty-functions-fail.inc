<?php

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
