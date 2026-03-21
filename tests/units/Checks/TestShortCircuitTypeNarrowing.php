<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Test short-circuit type narrowing for &&, ||, and, or operators
 */
class TestShortCircuitTypeNarrowing extends TestSuiteSetup {

	// ========== && (BooleanAnd) Tests ==========

	public function testInstanceOfAndMethodCall() {
		$func = <<<'ENDCODE'
			class TestClass {
				public function isReadonly(): bool { return false; }
			}
			function foo(?TestClass $obj) {
				if ($obj instanceof TestClass && $obj->isReadonly()) {
					echo "readonly";
				}
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_METHOD_CALL, ["basePath"=>"/"]);
		$this->assertEquals(0, $output->getErrorCount(), "instanceof should narrow type for right side of &&");
	}

	public function testNotNullAndMethodCall() {
		$func = <<<'ENDCODE'
			class TestClass {
				public function count(): int { return 0; }
			}
			function foo(?TestClass $obj) {
				if ($obj !== null && $obj->count() > 0) {
					echo "has items";
				}
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_METHOD_CALL, ["basePath"=>"/"]);
		$this->assertEquals(0, $output->getErrorCount(), "!== null should narrow type for right side of &&");
	}

	public function testTruthyVariableAndMethodCall() {
		$func = <<<'ENDCODE'
			class TestClass {
				public function process(): void {}
			}
			function foo(?TestClass $obj) {
				if ($obj && $obj->process()) {
					echo "processed";
				}
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_METHOD_CALL, ["basePath"=>"/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Truthy check should narrow type for right side of &&");
	}

	public function testChainedAndConditions() {
		$func = <<<'ENDCODE'
			class TestClass {
				public function isValid(): bool { return true; }
				public function process(): void {}
			}
			function foo(?TestClass $obj) {
				if ($obj !== null && $obj->isValid() && $obj->process()) {
					echo "done";
				}
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_METHOD_CALL, ["basePath"=>"/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Chained && should maintain narrowing");
	}

	// ========== || (BooleanOr) Tests ==========

	public function testNullOrMethodCall() {
		$func = <<<'ENDCODE'
			class TestClass {
				public function isEmpty(): bool { return false; }
			}
			function foo(?TestClass $obj) {
				if ($obj === null || $obj->isEmpty()) {
					echo "empty or null";
				}
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_METHOD_CALL, ["basePath"=>"/"]);
		$this->assertEquals(0, $output->getErrorCount(), "=== null should narrow type for right side of ||");
	}

	public function testNotInstanceOfOrMethodCall() {
		$func = <<<'ENDCODE'
			class TestClass {
				public function process(): void {}
			}
			class OtherClass {}
			function foo($obj) {
				if (!($obj instanceof TestClass) || $obj->process()) {
					echo "processed or not TestClass";
				}
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_UNKNOWN_CLASS, ["basePath"=>"/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Negated instanceof should narrow for right side of ||");
	}

	// ========== "and" (LogicalAnd) Tests ==========

	public function testLogicalAndInstanceOf() {
		$func = <<<'ENDCODE'
			class TestClass {
				public function isReadonly(): bool { return false; }
			}
			function foo(?TestClass $obj) {
				if ($obj instanceof TestClass and $obj->isReadonly()) {
					echo "readonly";
				}
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_METHOD_CALL, ["basePath"=>"/"]);
		$this->assertEquals(0, $output->getErrorCount(), "instanceof should narrow type for right side of 'and'");
	}

	public function testLogicalAndNotNull() {
		$func = <<<'ENDCODE'
			class TestClass {
				public function count(): int { return 0; }
			}
			function foo(?TestClass $obj) {
				if ($obj !== null and $obj->count() > 0) {
					echo "has items";
				}
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_METHOD_CALL, ["basePath"=>"/"]);
		$this->assertEquals(0, $output->getErrorCount(), "!== null should narrow type for right side of 'and'");
	}

	// ========== "or" (LogicalOr) Tests ==========

	public function testLogicalOrNull() {
		$func = <<<'ENDCODE'
			class TestClass {
				public function isEmpty(): bool { return false; }
			}
			function foo(?TestClass $obj) {
				if ($obj === null or $obj->isEmpty()) {
					echo "empty or null";
				}
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_METHOD_CALL, ["basePath"=>"/"]);
		$this->assertEquals(0, $output->getErrorCount(), "=== null should narrow type for right side of 'or'");
	}

	// ========== Never Returns Tests ==========

	public function testOrWithExit() {
		$func = <<<'ENDCODE'
			function getValue(): ?string {
				return null;
			}
			function foo() {
				$value = getValue() || exit(1);
				// After this line, $value is known to be truthy (not null/false)
				return strlen($value);
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath"=>"/"]);
		$this->assertEquals(0, $output->getErrorCount(), "|| exit() should narrow left side to truthy");
	}

	public function testAndWithThrow() {
		$func = <<<'ENDCODE'
			function foo(?string $value) {
				$value === null && throw new \Exception("Value required");
				// After this line, $value is known to be non-null
				return strlen($value);
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath"=>"/"]);
		$this->assertEquals(0, $output->getErrorCount(), "&& throw should narrow left side to falsy (non-null)");
	}

	// ========== Expression Context Tests ==========

	public function testShortCircuitInReturnStatement() {
		$func = <<<'ENDCODE'
			class TestClass {
				public function isValid(): bool { return true; }
			}
			function foo(?TestClass $obj): bool {
				return $obj instanceof TestClass && $obj->isValid();
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_METHOD_CALL, ["basePath"=>"/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Short-circuit in return should narrow types");
	}

	public function testShortCircuitInAssignment() {
		$func = <<<'ENDCODE'
			class TestClass {
				public function getValue(): int { return 42; }
			}
			function foo(?TestClass $obj) {
				$result = $obj !== null && $obj->getValue() > 0;
				return $result;
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_METHOD_CALL, ["basePath"=>"/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Short-circuit in assignment should narrow types");
	}

	public function testShortCircuitInFunctionArgument() {
		$func = <<<'ENDCODE'
			class TestClass {
				public function isReady(): bool { return true; }
			}
			function process(bool $ready) {}
			function foo(?TestClass $obj) {
				process($obj !== null && $obj->isReady());
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_METHOD_CALL, ["basePath"=>"/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Short-circuit in function argument should narrow types");
	}

	// ========== Multiple Variables Tests ==========

	public function testMultipleVariablesWithAnd() {
		$func = <<<'ENDCODE'
			class TestClass {
				public function compare(TestClass $other): bool { return true; }
			}
			function foo(?TestClass $a, ?TestClass $b) {
				if ($a !== null && $b !== null && $a->compare($b)) {
					echo "compared";
				}
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_METHOD_CALL, ["basePath"=>"/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Multiple variables should be narrowed independently");
	}

	// ========== Negative Tests (should error) ==========

	public function testNoNarrowingAfterExpression() {
		$func = <<<'ENDCODE'
			class TestClass {
				public function process(): void {}
			}
			function foo(?TestClass $obj) {
				$obj instanceof TestClass && $obj->process();
				// After the expression, narrowing doesn't persist
				$obj->process();
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_METHOD_CALL, ["basePath"=>"/"]);
		$this->assertEquals(1, $output->getErrorCount(), "Narrowing should not persist after short-circuit expression");
	}

	public function testLeftSideNotNarrowed() {
		$func = <<<'ENDCODE'
			class TestClass {
				public function isValid(): bool { return true; }
			}
			function foo(?TestClass $obj) {
				if ($obj->isValid() && $obj !== null) {
					echo "valid";
				}
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_METHOD_CALL, ["basePath"=>"/"]);
		$this->assertEquals(1, $output->getErrorCount(), "Left side should not be narrowed");
	}
}
