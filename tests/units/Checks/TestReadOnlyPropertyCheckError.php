<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Test to reproduce the exact error on ReadOnlyPropertyCheck.php line 21
 */
class TestReadOnlyPropertyCheckError extends TestSuiteSetup {

	public function testAnalyzeReadOnlyPropertyCheck() {
		$fileName = "src/Checks/ReadOnlyPropertyCheck.php";
		$fileData = file_get_contents(__DIR__ . '/../../../' . $fileName);
		
		// Create output that stores errors
		$output = new class(new \BambooHR\Guardrail\Tests\TestConfig($fileName, [], ["basePath"=>"/"])) extends \BambooHR\Guardrail\Output\XUnitOutput implements \BambooHR\Guardrail\Metrics\MetricOutputInterface {
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

		$config = new \BambooHR\Guardrail\Tests\TestConfig($fileName, [], ["basePath"=>__DIR__ . '/../../..']);
		$indexer = new \BambooHR\Guardrail\Phases\IndexingPhase($config, $output);
		$indexer->indexString($fileName, $fileData);

		$analyzer = new \BambooHR\Guardrail\Phases\AnalyzingPhase();
		$analyzer->initParser($config, $output);
		$analyzer->analyzeString($fileName, $fileData, $config);
		
		echo "\n========== ReadOnlyPropertyCheck.php Analysis ==========\n";
		echo "Total errors: " . count($output->capturedErrors) . "\n\n";
		
		if (count($output->capturedErrors) > 0) {
			foreach ($output->capturedErrors as $error) {
				echo sprintf("Line %d [%s]: %s\n", $error['line'], $error['type'], $error['message']);
			}
		} else {
			echo "✓ No errors found\n";
		}
		echo "\n";
		
		// Check specifically for line 21 errors
		$line21Errors = array_filter($output->capturedErrors, fn($e) => $e['line'] == 21);
		if (!empty($line21Errors)) {
			echo "ERROR ON LINE 21:\n";
			foreach ($line21Errors as $error) {
				echo "  Type: " . $error['type'] . "\n";
				echo "  Message: " . $error['message'] . "\n";
			}
		}
	}
}
