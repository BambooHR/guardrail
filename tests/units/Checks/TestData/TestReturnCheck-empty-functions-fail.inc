<?php declare(strict_types=1);

class EmptyFunctionsFail {
	function intReturn(): int {}

	function stringReturn(): string {}

	function arrayReturn(): array {}

	function boolReturn(): bool {}

	function callableReturn(): callable {}

	function objectReturn(): object {}

	function selfReturn(): self {}

	function staticReturn(): static {}

	function iterableReturn(): iterable {}

	function throwableReturn(): Throwable {}

	function nullableIntReturn(): ?int {}

	function unionTypeReturn(): int | string {}

	function nullReturn(): null {}

	function nullableStringReturn(): null|string {}
}

function intReturn(): int {}

function stringReturn(): string {}

function arrayReturn(): array {}

function boolReturn(): bool {}

function callableReturn(): callable {}

function objectReturn(): object {}

function selfReturn(): self {}

function staticReturn(): static {}

function iterableReturn(): iterable {}

function throwableReturn(): Throwable {}

function nullableIntReturn(): ?int {}

function unionTypeReturn(): int | string {}

function nullReturn(): null {}

function nullableStringReturn(): null|string {}
