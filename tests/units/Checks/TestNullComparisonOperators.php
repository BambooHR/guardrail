<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Test that both == and === (and != and !==) work for null comparisons
 */
class TestNullComparisonOperators extends TestSuiteSetup {
	
	public function testIdenticalNull() {
		$func = <<<'ENDCODE'
			function test($x) {
				if ($x === null) {
					return;
				}
				return $x->method();
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - === null guards the usage");
	}
	
	public function testEqualNull() {
		$func = <<<'ENDCODE'
			function test($x) {
				if ($x == null) {
					return;
				}
				return $x->method();
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - == null guards the usage");
	}
	
	public function testNotIdenticalNull() {
		$func = <<<'ENDCODE'
			function test($x) {
				if ($x !== null) {
					return $x->method();
				}
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - !== null narrows to non-null");
	}
	
	public function testNotEqualNull() {
		$func = <<<'ENDCODE'
			function test($x) {
				if ($x != null) {
					return $x->method();
				}
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - != null narrows to non-null");
	}
	
	public function testNullIdenticalWithEarlyReturn() {
		$func = <<<'ENDCODE'
			function test($x) {
				if (null === $x) {
					return;
				}
				return $x->method();
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - null === \$x guards the usage");
	}
	
	public function testNullEqualWithEarlyReturn() {
		$func = <<<'ENDCODE'
			function test($x) {
				if (null == $x) {
					return;
				}
				return $x->method();
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - null == \$x guards the usage");
	}
	
	public function testNullNotIdentical() {
		$func = <<<'ENDCODE'
			function test($x) {
				if (null !== $x) {
					return $x->method();
				}
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - null !== \$x narrows to non-null");
	}
	
	public function testNullNotEqual() {
		$func = <<<'ENDCODE'
			function test($x) {
				if (null != $x) {
					return $x->method();
				}
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath" => "/"]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - null != \$x narrows to non-null");
	}
}
