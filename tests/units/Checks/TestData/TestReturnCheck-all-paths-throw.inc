<?php declare(strict_types=1);

function testFunction(): int {
	throw new \Exception();
}

function testFunction2(): int {
	if (true) {
		throw new \Exception();
	} elseif (true) {
		throw new \Exception();
	} else {
		throw new \Exception();
	}
}

function testSwitchAllThrow(int $x): string {
	switch ($x) {
		case 1:
			throw new \Exception("case 1");
		case 2:
			throw new \RuntimeException("case 2");
		default:
			throw new \LogicException("default");
	}
}

function testNestedIfAllThrow(bool $a, bool $b): array {
	if ($a) {
		if ($b) {
			throw new \Exception("a and b");
		} elseif ($b) {
			throw new \Exception("a not b");
		} else {
			throw new \Exception("a not b");
		}
		throw new \Exception("a");
	} elseif ($b) {
		if ($a) {
			throw new \Exception("not a");
		} elseif ($a) {
			throw new \Exception("not a");
		} else {
			throw new \Exception("not a");
		}
		throw new \Exception("b");
	} else {
		if (true) {
			throw new \Exception("not a");
		} elseif (true) {
			throw new \Exception("not a");
		} else {
			throw new \Exception("not a");
		}
		throw new \Exception("not a");
	}
	throw new \Exception("not a");
}

function testSimpleIfElseThrow(bool $condition): bool {
	if ($condition) {
		throw new \Exception("true");
	} else {
		throw new \Exception("false");
	}
}

class TestClass {
	public function methodAllThrow(): int {
		throw new \Exception();
	}

	public function methodIfElseThrow(bool $flag): string {
		if ($flag) {
			throw new \Exception("flag true");
		} else {
			throw new \Exception("flag false");
		}
	}

	public function methodSwitchThrow(int $value): array {
		switch ($value) {
			case 0:
				throw new \Exception("zero");
			case 1:
				throw new \Exception("one");
			default:
				throw new \Exception("other");
		}
	}

	private static function staticMethodThrow(): object {
		throw new \Exception();
	}
}

// Try-catch where both try and catch throw
function tryCatchBothThrow(): int {
	try {
		throw new \Exception("try");
	} catch (\Exception $e) {
		throw new \RuntimeException("catch");
	}
}

// Try-catch where both try and catch return
function tryCatchBothReturn(): string {
	try {
		return "try";
	} catch (\Exception $e) {
		return "catch";
	}
}

// Try-catch where try returns and catch throws
function tryCatchReturnAndThrow(): array {
	try {
		return [];
	} catch (\Exception $e) {
		throw new \RuntimeException("catch");
	}
}

// Try-catch with multiple catch blocks, all throw
function tryCatchMultipleCatchesThrow(int $x): bool {
	try {
		throw new \Exception("try");
	} catch (\RuntimeException $e) {
		throw new \LogicException("runtime");
	} catch (\Exception $e) {
		throw new \InvalidArgumentException("exception");
	}
}

// Try-catch with multiple catch blocks, all return
function tryCatchMultipleCatchesReturn(int $x): string {
	try {
		return "try";
	} catch (\RuntimeException $e) {
		return "runtime";
	} catch (\Exception $e) {
		return "exception";
	}
}

class TryCatchTestClass {
	public function methodTryCatchThrow(): int {
		try {
			throw new \Exception();
		} catch (\Exception $e) {
			throw new \RuntimeException();
		}
	}

	public function methodTryCatchReturn(): string {
		try {
			return "try";
		} catch (\Exception $e) {
			return "catch";
		}
	}

	public function methodTryCatchMixed(): array {
		try {
			return [];
		} catch (\RuntimeException $e) {
			throw new \Exception();
		} catch (\Exception $e) {
			return ["error"];
		}
	}
}

// Try-catch-finally where try and catch both return
function tryCatchFinallyBothReturn(): int {
	try {
		return 1;
	} catch (\Exception $e) {
		return 0;
	} finally {
		// Cleanup code
		$log = "done";
	}
}

// Try-catch-finally where try and catch both throw
function tryCatchFinallyBothThrow(): string {
	try {
		throw new \Exception("try");
	} catch (\Exception $e) {
		throw new \RuntimeException("catch");
	} finally {
		// Cleanup code
	}
}

// Try-catch-finally with multiple catches, all return
function tryCatchFinallyMultipleCatchesReturn(): array {
	try {
		return [];
	} catch (\RuntimeException $e) {
		return ["runtime"];
	} catch (\Exception $e) {
		return ["exception"];
	} finally {
		// Cleanup
	}
}

class TryCatchFinallyTestClass {
	public function methodWithFinally(): bool {
		try {
			return true;
		} catch (\Exception $e) {
			return false;
		} finally {
			// Cleanup
		}
	}
}

// Finally returns, overriding try/catch (try/catch don't complete)
function finallyReturnsOverride(): int {
	try {
		$x = 5;
	} catch (\Exception $e) {
		$y = 10;
	} finally {
		return 42; // Finally return overrides
	}
}

// Try throws, catch returns, finally doesn't need to
function tryThrowsCatchReturns(): array {
	try {
		throw new \Exception();
	} catch (\Exception $e) {
		return [];
	} finally {
		// Cleanup
	}
}

// Try throws, catch doesn't return, finally returns (overrides)
function tryThrowsCatchNoReturnFinallyReturns(): bool {
	try {
		throw new \Exception();
	} catch (\Exception $e) {
		$log = $e->getMessage();
	} finally {
		return true; // Finally return overrides
	}
}

// Finally throws, overriding try/catch
function finallyThrowsOverride(): int {
	try {
		$x = 5;
	} catch (\Exception $e) {
		$y = 10;
	} finally {
		throw new \RuntimeException(); // Finally throw overrides
	}
}

// Try throws, catch doesn't return (but finally does)
function tryThrowsCatchNoReturnFinallyThrows(): string {
	try {
		throw new \Exception();
	} catch (\Exception $e) {
		$log = $e->getMessage();
	} finally {
		throw new \RuntimeException(); // Finally throw overrides
	}
}

// Try throws, catch throws, finally doesn't need to
function tryThrowsCatchThrows(): array {
	try {
		throw new \Exception();
	} catch (\Exception $e) {
		throw new \RuntimeException();
	} finally {
		// Cleanup
	}
}

// Try throws, catch doesn't throw, finally throws (overrides)
function tryThrowsCatchNoThrowFinallyThrows(): bool {
	try {
		throw new \Exception();
	} catch (\Exception $e) {
		$log = $e->getMessage();
	} finally {
		throw new \RuntimeException(); // Finally throw overrides
	}
}

// Function that uses exit() to terminate
function functionWithExit(): int {
	if (true) {
		exit(1);
	} else {
		return 0;
	}
}

// Function where all paths use exit()
function allPathsExit(bool $condition): string {
	if ($condition) {
		exit(0);
	} else {
		exit(1);
	}
}

// Switch statement with break statements that all return/throw
function switchWithBreaksAllReturn(int $x): array {
	switch ($x) {
		case 1:
			return [1];
			break;
		case 2:
			return [2];
			break;
		default:
			return [];
			break;
	}
}