<?php declare(strict_types=1);

// Function with no return and no throw - should fail
function noReturnNoThrow(): int {
	$x = 5;
}

// Function where only some paths throw - should fail
function somePathsThrow(bool $condition): string {
	if ($condition) {
		throw new \Exception();
	}
	// Missing else branch - no return or throw
}

// Function with if/else but only one branch throws - should fail
function onlyIfThrows(bool $flag): array {
	if ($flag) {
		throw new \Exception();
	} else {
		$x = [];
		// No return or throw in else
	}
}

// Switch without default case - should fail
function switchNoDefault(int $x): bool {
	switch ($x) {
		case 1:
			throw new \Exception();
		case 2:
			throw new \Exception();
		// Missing default case
	}
}

// Switch with default but not all cases throw - should fail
function switchNotAllThrow(int $x): string {
	switch ($x) {
		case 1:
			throw new \Exception();
		case 2:
			return "two";
		default:
			// No return or throw in default
			$y = "default";
	}
}

// Nested if where inner branch doesn't throw - should fail
function nestedIfPartialThrow(bool $a, bool $b): int {
	if ($a) {
		if ($b) {
			throw new \Exception();
		}
		// Missing else for inner if
	} else {
		throw new \Exception();
	}
}

// Function with early return but last path doesn't throw or return - should fail
function earlyReturnButNoFinalPath(int $x): string {
	if ($x > 10) {
		return "big";
	}
	// No return or throw for x <= 10
}

class TestFailClass {
	// Method with no return and no throw - should fail
	public function noReturnNoThrow(): int {
		$value = 42;
	}

	// Method where only some paths throw - should fail
	public function somePathsThrow(bool $condition): string {
		if ($condition) {
			throw new \Exception();
		}
		// Missing else - no return or throw
	}

	// Method with switch missing default - should fail
	public function switchNoDefault(int $x): array {
		switch ($x) {
			case 1:
				throw new \Exception();
			case 2:
				throw new \Exception();
		}
	}

	// Static method with incomplete paths - should fail
	private static function incompleteThrow(bool $flag): object {
		if ($flag) {
			throw new \Exception();
		}
		// No else branch
	}

	// Method with elseif but no else - should fail
	public function elseifNoElse(int $x): bool {
		if ($x > 0) {
			throw new \Exception();
		} elseif ($x < 0) {
			throw new \Exception();
		}
		// Missing else for x == 0
	}

	// Method with nested conditions, partial coverage - should fail
	protected function nestedPartial(bool $a, bool $b): string {
		if ($a) {
			if ($b) {
				return "both";
			} else {
				throw new \Exception();
			}
		}
		// Missing else for outer if
	}
}

// Try-catch where try doesn't return/throw - should fail
function tryCatchTryNoReturn(): int {
	try {
		$x = 5;
		// No return or throw in try
	} catch (\Exception $e) {
		return 1;
	}
}

// Try-catch where catch doesn't return/throw - should fail
function tryCatchCatchNoReturn(): string {
	try {
		return "try";
	} catch (\Exception $e) {
		$error = $e->getMessage();
		// No return or throw in catch
	}
}

// Try-catch with multiple catches, one missing return/throw - should fail
function tryCatchOneCatchMissing(): array {
	try {
		return [];
	} catch (\RuntimeException $e) {
		return ["runtime"];
	} catch (\Exception $e) {
		$log = $e->getMessage();
		// No return or throw in this catch
	}
}

class TryCatchFailClass {
	// Try block doesn't return/throw - should fail
	public function tryCatchTryIncomplete(): int {
		try {
			$value = 42;
		} catch (\Exception $e) {
			throw new \RuntimeException();
		}
	}

	// Catch block doesn't return/throw - should fail
	public function tryCatchCatchIncomplete(): string {
		try {
			throw new \Exception();
		} catch (\Exception $e) {
			$error = $e->getMessage();
		}
	}

	// Multiple catches, one incomplete - should fail
	public function tryCatchMultipleCatchesIncomplete(): array {
		try {
			return [];
		} catch (\RuntimeException $e) {
			throw new \Exception();
		} catch (\LogicException $e) {
			$log = $e->getMessage();
			// Missing return/throw
		}
	}
}

// Try-catch-finally where try incomplete, catch incomplete, finally doesn't help - should fail
function finallyNoHelpBothIncomplete(): int {
	try {
		$x = 5;
		// No return or throw
	} catch (\Exception $e) {
		$error = $e->getMessage();
		// No return or throw
	} finally {
		// Cleanup only, no return/throw
		$log = "done";
	}
}

// Try-catch-finally where try returns, catch incomplete, finally doesn't help - should fail
function finallyNoHelpCatchIncomplete(): string {
	try {
		return "success";
	} catch (\Exception $e) {
		$error = $e->getMessage();
		// No return or throw in catch
	} finally {
		// Cleanup only
	}
}

// Try-catch-finally where try incomplete, catch returns, finally doesn't help - should fail
function finallyNoHelpTryIncomplete(): array {
	try {
		$x = [];
		// No return or throw
	} catch (\Exception $e) {
		return ["error"];
	} finally {
		// Cleanup only
	}
}

// Try-catch-finally with multiple catches, one incomplete, finally doesn't help - should fail
function finallyNoHelpOneCatchIncomplete(): bool {
	try {
		throw new \Exception();
	} catch (\RuntimeException $e) {
		return true;
	} catch (\Exception $e) {
		$log = $e->getMessage();
		// No return or throw
	} finally {
		// Cleanup only
	}
}

// Try-catch-finally where finally itself is incomplete (no return/throw) - should fail
function finallyIncompleteNoReturnThrow(): int {
	try {
		$x = 5;
	} catch (\Exception $e) {
		$y = 10;
	} finally {
		$cleanup = true;
		// Finally doesn't return or throw, and neither do try/catch
	}
}

// Try-catch-finally where try throws, catch incomplete, finally incomplete - should fail
function finallyIncompleteCatchIncomplete(): string {
	try {
		throw new \Exception();
	} catch (\Exception $e) {
		$error = $e->getMessage();
		// No return or throw
	} finally {
		$cleanup = true;
		// Finally doesn't return or throw
	}
}

// Function with elseif where one branch is incomplete - should fail
function elseifBranchIncomplete(int $x): string {
	if ($x > 0) {
		return "positive";
	} elseif ($x < 0) {
		$log = "negative";
		// No return or throw in elseif
	} else {
		return "zero";
	}
}

// Nested try-catch where inner catch is incomplete - should fail
function nestedTryCatchIncomplete(): int {
	try {
		try {
			throw new \Exception();
		} catch (\Exception $e) {
			$log = $e->getMessage();
			// Inner catch doesn't return/throw
		}
	} catch (\Exception $e) {
		return 2;
	}
}

// Switch inside if where switch is incomplete - should fail
function switchInsideIfIncomplete(bool $flag, int $x): string {
	if ($flag) {
		switch ($x) {
			case 1:
				return "one";
			// Missing default case
		}
	} else {
		return "false";
	}
}

// If inside switch where if is incomplete - should fail
function ifInsideSwitchIncomplete(int $x, bool $flag): array {
	switch ($x) {
		case 1:
			if ($flag) {
				return [1];
			}
			// Missing else branch
		default:
			return [];
	}
}

// Try-catch inside if where catch is incomplete - should fail
function tryCatchInsideIfIncomplete(bool $flag): int {
	if ($flag) {
		try {
			throw new \Exception();
		} catch (\Exception $e) {
			$error = $e->getMessage();
			// Catch doesn't return/throw
		}
	} else {
		return 0;
	}
}

// Try-finally where try doesn't return and finally doesn't help - should fail
function tryFinallyIncomplete(): int {
	try {
		$x = 5;
		// No return or throw
	} finally {
		// cleanup only
	}
}

// Deeply nested where innermost is incomplete - should fail
function deeplyNestedIncomplete(int $a, int $b, int $c): string {
	if ($a > 0) {
		if ($b > 0) {
			switch ($c) {
				case 1:
					return "1";
				// Missing default case
			}
		} else {
			return "b negative";
		}
	} else {
		return "a negative";
	}
}

class FinallyFailClass {
	// Try-catch-finally where both try and catch incomplete, finally doesn't help - should fail
	public function finallyNoHelpBothIncomplete(): int {
		try {
			$value = 42;
		} catch (\Exception $e) {
			$error = $e->getMessage();
		} finally {
			// Cleanup only
		}
	}

	// Try-catch-finally where catch incomplete, finally doesn't help - should fail
	public function finallyNoHelpCatchIncomplete(): string {
		try {
			return "try";
		} catch (\Exception $e) {
			$error = $e->getMessage();
			// No return or throw
		} finally {
			// Cleanup only
		}
	}

	// Try-catch-finally where finally is incomplete - should fail
	public function finallyIncomplete(): array {
		try {
			$x = [];
		} catch (\Exception $e) {
			$y = [];
		} finally {
			$cleanup = true;
			// No return or throw anywhere
		}
	}

	// Try-catch-finally with multiple catches, one incomplete, finally doesn't help - should fail
	public function finallyNoHelpMultipleCatchesIncomplete(): bool {
		try {
			throw new \Exception();
		} catch (\RuntimeException $e) {
			return true;
		} catch (\LogicException $e) {
			$log = $e->getMessage();
			// No return or throw
		} finally {
			// Cleanup only
		}
	}
}
