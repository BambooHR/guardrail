<?php declare(strict_types=1);

// Empty functions that should PASS (no return required)
class EmptyFunctionsPass {
	function noneReturn(): none {}

	function voidReturn(): void {}

	function neverReturn(): never {}

	function nullReturn(): null {}

	function nullableReturn(): ?int {}

	function nullableStringReturn(): null|string {}

	function mixedReturn(): mixed {}
}

// Abstract method - should pass
abstract class AbstractMethods {
	abstract function method(): int;
}
