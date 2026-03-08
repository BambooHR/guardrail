<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Test flow-sensitive type narrowing and nullability detection
 */
class TestNullabilityCheck extends TestSuiteSetup {
	
	// ========================================
	// Basic Null Checks
	// ========================================
	
	public function testNullCheckWithNotEqual() {
		$func = <<<'ENDCODE'
			function test($x) {
				if ($x !== null) {
					return $x->method();
				}
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - \$x is narrowed to non-null");
	}
	
	public function testNullCheckWithEarlyReturn() {
		$func = <<<'ENDCODE'
			function test($x) {
				if ($x === null) {
					return;
				}
				return $x->method();
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - early return eliminates null");
	}
	
	public function testNullDereferenceWithoutCheck() {
		$func = <<<'ENDCODE'
			function test($x) {
				return $x->method();
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_METHOD_CALL, ["basePath" => "/"]);
		$this->assertGreaterThan(0, $output->getErrorCount(), "Should error - \$x may be null");
	}
	
	public function testIssetCheck() {
		$func = <<<'ENDCODE'
			function test($x) {
				if (isset($x)) {
					return $x->method();
				}
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - isset proves non-null");
	}
	
	public function testIsNullCheck() {
		$func = <<<'ENDCODE'
			function test($x) {
				if (!is_null($x)) {
					return $x->method();
				}
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - !is_null proves non-null");
	}
	
	public function testInstanceOfCheck() {
		$func = <<<'ENDCODE'
			function test($x) {
				if ($x instanceof \stdClass) {
					return $x->method();
				}
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - instanceof proves non-null");
	}
	
	public function testTruthyCheck() {
		$func = <<<'ENDCODE'
			function test($x) {
				if ($x) {
					return $x->method();
				}
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - truthy check proves non-null");
	}
	
	// ========================================
	// If/Else Branch Merging
	// ========================================
	
	public function testIfElseBothBranchesNonNull() {
		$func = <<<'ENDCODE'
			function test($condition) {
				if ($condition) {
					$x = new \stdClass();
				} else {
					$x = new \stdClass();
				}
				return $x->method();
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - \$x is non-null in both branches");
	}
	
	public function testIfElseOneBranchNull() {
		$func = <<<'ENDCODE'
			function test($condition, $y) {
				if ($condition) {
					$x = new \stdClass();
				} else {
					$x = $y;
				}
				return $x->method();
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_METHOD_CALL, ["basePath" => "/"]);
		$this->assertGreaterThan(0, $output->getErrorCount(), "Should error - \$x may be null from else branch");
	}
	
	public function testIfWithoutElseNullable() {
		$func = <<<'ENDCODE'
			function test($condition) {
				$x = null;
				if ($condition) {
					$x = new \stdClass();
				}
				return $x->method();
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_METHOD_CALL, ["basePath" => "/"]);
		$this->assertGreaterThan(0, $output->getErrorCount(), "Should error - \$x may still be null");
	}
	
	// ========================================
	// Undefined Variable Detection
	// ========================================
	
	public function testVariableDefinedInOneBranch() {
		$func = <<<'ENDCODE'
			function test($condition) {
				if ($condition) {
					$x = "value";
				}
				return $x;
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_INCONSISTENT_VARIABLE, ["basePath" => "/"]);
		$this->assertGreaterThan(0, $output->getErrorCount(), "Should error - \$x may be unset");
	}
	
	public function testVariableDefinedInAllBranches() {
		$func = <<<'ENDCODE'
			function test($condition) {
				if ($condition) {
					$x = "one";
				} else {
					$x = "two";
				}
				return $x;
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_UNKNOWN_VARIABLE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - \$x is defined in all branches");
	}
	
	// ========================================
	// Switch Statement Tests
	// ========================================
	
	public function testSwitchWithDefault() {
		$func = <<<'ENDCODE'
			function test($value) {
				switch ($value) {
					case 1:
						$x = "one";
						break;
					case 2:
						$x = "two";
						break;
					default:
						$x = "other";
						break;
				}
				return $x;
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_UNKNOWN_VARIABLE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - \$x is set in all cases including default");
	}
	
	public function testSwitchWithoutDefault() {
		$func = <<<'ENDCODE'
			function test($value) {
				switch ($value) {
					case 1:
						$x = "one";
						break;
					case 2:
						$x = "two";
						break;
				}
				return $x;
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_INCONSISTENT_VARIABLE, ["basePath" => "/"]);
		$this->assertGreaterThan(0, $output->getErrorCount(), "Should error - \$x may be unset (no default)");
	}
	
	// ========================================
	// Loop Tests
	// ========================================
	
	public function testWhileLoopNarrowing() {
		$func = <<<'ENDCODE'
			function test($stmt) {
				while ($row = $stmt->fetch()) {
					$row->process();
				}
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - \$row is truthy inside loop");
	}
	
	public function testWhileLoopAfterExit() {
		$func = <<<'ENDCODE'
			function test($stmt) {
				while ($row = $stmt->fetch()) {
					// Inside loop
				}
				return $row->process();
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_METHOD_CALL, ["basePath" => "/"]);
		$this->assertGreaterThan(0, $output->getErrorCount(), "Should error - \$row is null/false after loop");
	}
	
	// ========================================
	// Try/Catch/Finally Tests
	// ========================================
	
	public function testTryBlockVariableMayBeUnset() {
		$func = <<<'ENDCODE'
			function test() {
				try {
					$x = getValue();
				} catch (\Exception $e) {
					// Exception
				}
				return $x;
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_INCONSISTENT_VARIABLE, ["basePath" => "/"]);
		$this->assertGreaterThan(0, $output->getErrorCount(), "Should error - \$x may be unset (try may have failed)");
	}
	
	public function testCatchBlockVariableMayBeUnset() {
		$func = <<<'ENDCODE'
			function test() {
				try {
					doSomething();
				} catch (\Exception $e) {
					$x = "error";
				}
				return $x;
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_INCONSISTENT_VARIABLE, ["basePath" => "/"]);
		$this->assertGreaterThan(0, $output->getErrorCount(), "Should error - \$x may be unset (only set if exception)");
	}
	
	public function testFinallyBlockVariableAlwaysSet() {
		$func = <<<'ENDCODE'
			function test() {
				try {
					doSomething();
				} finally {
					$x = "always";
				}
				return $x;
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_UNKNOWN_VARIABLE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - \$x is always set in finally");
	}
	
	public function testCatchExceptionVariableNonNull() {
		$func = <<<'ENDCODE'
			function test() {
				try {
					throw new \Exception("test");
				} catch (\Exception $e) {
					return $e->getMessage();
				}
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - exception variable is non-null");
	}
	
	// ========================================
	// Short-Circuit Evaluation Tests
	// ========================================
	
	public function testAndShortCircuit() {
		$func = <<<'ENDCODE'
			function test($x) {
				if ($x !== null && $x->isValid()) {
					return $x->process();
				}
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - \$x is non-null in both conditions");
	}
	
	public function testOrShortCircuitWithEarlyReturn() {
		$func = <<<'ENDCODE'
			function test($x) {
				if ($x === null || $x->isEmpty()) {
					return;
				}
				return $x->process();
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - both conditions failed, \$x is non-null and not empty");
	}
	
	public function testAssignmentInCondition() {
		$func = <<<'ENDCODE'
			function test() {
				if ($x = getValue()) {
					return $x->method();
				}
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - \$x is truthy in if block");
	}
	
	// ========================================
	// Type Check Functions
	// ========================================
	
	public function testIsStringNarrowing() {
		$func = <<<'ENDCODE'
			function test($x) {
				if (is_string($x)) {
					return strlen($x);
				}
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - is_string proves non-null");
	}
	
	public function testIsObjectNarrowing() {
		$func = <<<'ENDCODE'
			function test($x) {
				if (is_object($x)) {
					return $x->method();
				}
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - is_object proves non-null");
	}
	
	// ========================================
	// Negation Tests
	// ========================================
	
	public function testNotNullCheck() {
		$func = <<<'ENDCODE'
			function test($x) {
				if (!($x === null)) {
					return $x->method();
				}
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - !(\$x === null) proves non-null");
	}
	
	public function testDoubleNegation() {
		$func = <<<'ENDCODE'
			function test($x) {
				if (!!$x) {
					return $x->method();
				}
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - !!\$x proves truthy/non-null");
	}
	
	// ========================================
	// Complex Nested Scenarios
	// ========================================
	
	public function testNestedIfStatements() {
		$func = <<<'ENDCODE'
			function test($x, $y) {
				if ($x !== null) {
					if ($y !== null) {
						return $x->method() . $y->method();
					}
					return $x->method();
				}
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - both variables properly narrowed");
	}
	
	public function testMultipleEarlyReturns() {
		$func = <<<'ENDCODE'
			function test($x) {
				if ($x === null) {
					return;
				}
				if ($x->isEmpty()) {
					return;
				}
				return $x->process();
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - null eliminated by first check");
	}
	
	public function testIfInLoop() {
		$func = <<<'ENDCODE'
			function test($items) {
				foreach ($items as $item) {
					if ($item !== null) {
						$item->process();
					}
				}
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - \$item narrowed in if block");
	}
	
	public function testTryInIf() {
		$func = <<<'ENDCODE'
			function test($condition) {
				if ($condition) {
					try {
						$x = getValue();
					} catch (\Exception $e) {
						$x = "error";
					}
					return $x;
				}
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_INCONSISTENT_VARIABLE, ["basePath" => "/"]);
		$this->assertGreaterThan(0, $output->getErrorCount(), "Should error - \$x may be unset in try block");
	}
	
	// ========================================
	// Edge Cases
	// ========================================
	
	public function testEmptyIfBlock() {
		$func = <<<'ENDCODE'
			function test($x) {
				if ($x !== null) {
					// Empty
				}
				return $x->method();
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_METHOD_CALL, ["basePath" => "/"]);
		$this->assertGreaterThan(0, $output->getErrorCount(), "Should error - narrowing doesn't persist after empty if");
	}
	
	public function testTernaryNarrowing() {
		$func = <<<'ENDCODE'
			function test($x) {
				$result = $x !== null ? $x->getValue() : "default";
				return $result;
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - \$x narrowed in ternary true branch");
	}
	
	public function testComplexBooleanExpression() {
		$func = <<<'ENDCODE'
			function test($x, $y, $z) {
				if ($x !== null && $y !== null && $z !== null) {
					return $x->a() . $y->b() . $z->c();
				}
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - all three variables narrowed");
	}
}
