<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Test to capture and display all errors from short-circuit type narrowing tests
 */
class TestShortCircuitErrors extends TestSuiteSetup {

	private function analyzeAndShowErrors(string $code, string $testName): void {
		$fileName = "test.php";
		$config = new \BambooHR\Guardrail\Tests\TestConfig($fileName, [ErrorConstants::TYPE_NULL_METHOD_CALL], ["basePath"=>"/"]);
		
		// Create output that stores errors
		$output = new class($config) extends \BambooHR\Guardrail\Output\XUnitOutput implements \BambooHR\Guardrail\Metrics\MetricOutputInterface {
			public array $capturedErrors = [];
			
			function emitMetric(\BambooHR\Guardrail\Metrics\MetricInterface $metric): void {
				return;
			}
			
			function emitError($className, $file, $line, $type, $message = "") {
				$this->capturedErrors[] = [
					'file' => $file,
					'line' => $line,
					'type' => $type,
					'message' => $message,
					'class' => $className
				];
				parent::emitError($className, $file, $line, $type, $message);
			}
		};

		$indexer = new \BambooHR\Guardrail\Phases\IndexingPhase($config, $output);
		$indexer->indexString($fileName, $code);

		$analyzer = new \BambooHR\Guardrail\Phases\AnalyzingPhase();
		$analyzer->initParser($config, $output);
		$analyzer->analyzeString($fileName, $code, $config);
		
		echo "\n========== $testName ==========\n";
		echo "Error count: " . count($output->capturedErrors) . "\n";
		
		if (count($output->capturedErrors) > 0) {
			foreach ($output->capturedErrors as $error) {
				echo sprintf("  Line %d [%s]: %s\n", $error['line'], $error['type'], $error['message']);
			}
		} else {
			echo "  ✓ No errors\n";
		}
		echo "\n";
	}

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

		$this->analyzeAndShowErrors($func, "instanceof && method call");
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

		$this->analyzeAndShowErrors($func, "!== null && method call");
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

		$this->analyzeAndShowErrors($func, "truthy variable && method call");
	}

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

		$this->analyzeAndShowErrors($func, "=== null || method call");
	}

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

		$this->analyzeAndShowErrors($func, "'and' operator with instanceof");
	}

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

		$this->analyzeAndShowErrors($func, "'or' operator with null check");
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

		$this->analyzeAndShowErrors($func, "chained && conditions");
	}

	public function testShortCircuitInReturnStatement() {
		$func = <<<'ENDCODE'
			class TestClass {
				public function isValid(): bool { return true; }
			}
			function foo(?TestClass $obj): bool {
				return $obj instanceof TestClass && $obj->isValid();
			}
		ENDCODE;

		$this->analyzeAndShowErrors($func, "short-circuit in return statement");
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

		$this->analyzeAndShowErrors($func, "short-circuit in assignment");
	}

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

		$this->analyzeAndShowErrors($func, "multiple variables with &&");
	}
}
