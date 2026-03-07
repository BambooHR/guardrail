<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Test InconsistentVariableCheck - detects variables that may not be defined in all code paths
 */
class TestInconsistentVariableCheck extends TestSuiteSetup {
	
	// ========================================
	// Should NOT trigger - variable is always defined
	// ========================================
	
	public function testVariableDefinedBeforeIf() {
		$func = <<<'ENDCODE'
			function test($condition) {
				$x = "initial";
				if ($condition) {
					$x = "changed";
				}
				return $x;
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_INCONSISTENT_VARIABLE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - \$x is defined before if");
	}
	
	public function testVariableDefinedInAllBranches() {
		$func = <<<'ENDCODE'
			function test($condition) {
				if ($condition) {
					$x = "true branch";
				} else {
					$x = "false branch";
				}
				return $x;
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_INCONSISTENT_VARIABLE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - \$x is defined in both branches");
	}
	
	public function testVariableDefinedInAllElseIfBranches() {
		$func = <<<'ENDCODE'
			function test($value) {
				if ($value === 1) {
					$x = "one";
				} elseif ($value === 2) {
					$x = "two";
				} else {
					$x = "other";
				}
				return $x;
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_INCONSISTENT_VARIABLE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - \$x is defined in all branches");
	}
	
	public function testVariableDefinedInSwitchWithDefault() {
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
						$x = "default";
						break;
				}
				return $x;
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_INCONSISTENT_VARIABLE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - \$x is defined in all cases including default");
	}
	
	public function testVariableDefinedInFinally() {
		$func = <<<'ENDCODE'
			function test() {
				try {
					doSomething();
				} finally {
					$x = "always set";
				}
				return $x;
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_INCONSISTENT_VARIABLE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - \$x is always set in finally");
	}
	
	public function testFunctionParameter() {
		$func = <<<'ENDCODE'
			function test($x) {
				return $x;
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_INCONSISTENT_VARIABLE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - \$x is a function parameter");
	}
	
	// ========================================
	// SHOULD trigger - variable may be undefined
	// ========================================
	
	public function testVariableDefinedInOnlyOneBranch() {
		$func = <<<'ENDCODE'
			function test($condition) {
				if ($condition) {
					$x = "value";
				}
				return $x;
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_INCONSISTENT_VARIABLE, ["basePath" => "/"]);
		$this->assertGreaterThan(0, $output->getErrorCount(), "Should error - \$x may be unset if condition is false");
	}
	
	public function testVariableDefinedInOnlyElseBranch() {
		$func = <<<'ENDCODE'
			function test($condition) {
				if ($condition) {
					// nothing
				} else {
					$x = "value";
				}
				return $x;
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_INCONSISTENT_VARIABLE, ["basePath" => "/"]);
		$this->assertGreaterThan(0, $output->getErrorCount(), "Should error - \$x may be unset if condition is true");
	}
	
	public function testVariableDefinedInSomeElseIfBranches() {
		$func = <<<'ENDCODE'
			function test($value) {
				if ($value === 1) {
					$x = "one";
				} elseif ($value === 2) {
					$x = "two";
				}
				// No else - $x may be unset
				return $x;
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_INCONSISTENT_VARIABLE, ["basePath" => "/"]);
		$this->assertGreaterThan(0, $output->getErrorCount(), "Should error - \$x may be unset if value is not 1 or 2");
	}
	
	public function testVariableDefinedInSwitchWithoutDefault() {
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
		$this->assertGreaterThan(0, $output->getErrorCount(), "Should error - \$x may be unset if value doesn't match any case");
	}
	
	public function testVariableDefinedInTryBlock() {
		$func = <<<'ENDCODE'
			function test() {
				try {
					$x = getValue();
				} catch (\Exception $e) {
					// Exception caught, $x not set
				}
				return $x;
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_INCONSISTENT_VARIABLE, ["basePath" => "/"]);
		$this->assertGreaterThan(0, $output->getErrorCount(), "Should error - \$x may be unset if exception is thrown");
	}
	
	public function testVariableDefinedInCatchBlock() {
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
		$this->assertGreaterThan(0, $output->getErrorCount(), "Should error - \$x may be unset if no exception is thrown");
	}
	
	public function testVariableDefinedInNestedIf() {
		$func = <<<'ENDCODE'
			function test($a, $b) {
				if ($a) {
					if ($b) {
						$x = "value";
					}
				}
				return $x;
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_INCONSISTENT_VARIABLE, ["basePath" => "/"]);
		$this->assertGreaterThan(0, $output->getErrorCount(), "Should error - \$x may be unset if \$a or \$b is false");
	}
	
	// ========================================
	// Edge cases
	// ========================================
	
	public function testMultipleVariablesPartiallyDefined() {
		$func = <<<'ENDCODE'
			function test($condition) {
				$y = "always set";
				if ($condition) {
					$x = "sometimes set";
				}
				return $x . $y;
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_INCONSISTENT_VARIABLE, ["basePath" => "/"]);
		// Should only error for $x, not $y
		$this->assertGreaterThan(0, $output->getErrorCount(), "Should error for \$x but not \$y");
	}
	
	public function testVariableUsedInCondition() {
		$func = <<<'ENDCODE'
			function test($condition) {
				if ($condition) {
					$x = "value";
				}
				if (isset($x)) {
					return $x;
				}
				return null;
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_INCONSISTENT_VARIABLE, ["basePath" => "/"]);
		// isset() should guard against the error
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - isset() guards the usage");
	}
}
