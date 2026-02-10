<?php declare(strict_types=1);

// Empty functions that should PASS (no return required)
class EmptyFunctionsPass {
	function noneReturn(): none {}

	function noneReturnMultiLine(): none {
		// Empty function
	}

	function voidReturn(): void {}

	function neverReturn(): never {}

	function mixedReturn(): mixed {}
}

// Abstract method - should pass
abstract class AbstractMethods {
	abstract function method(): int;
}

function noneReturn(): none {}

function noneReturnMultiLine(): none {
    // Empty function
}

function voidReturn(): void {}

function neverReturn(): never {}

function mixedReturn(): mixed {}
