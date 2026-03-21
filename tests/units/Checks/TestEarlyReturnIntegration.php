<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Integration test for early return type narrowing through the full analyzer
 */
class TestEarlyReturnIntegration extends TestSuiteSetup {

	public function testEarlyReturnWithTruthyCheck() {
		$code = <<<'ENDCODE'
class MyClass {
	public string $value = "";
}

function processValue(?MyClass $obj): string {
	if (!$obj) {
		return "default";
	}
	// After the early return, $obj should be known to be non-null
	return $obj->value;
}
ENDCODE;
		
		$output = $this->analyzeStringToOutput("test.php", $code, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath"=>"/"]);
		
		// Should have 0 null access errors
		$this->assertEquals(0, $output->getErrorCount(), 
			"Early return with truthy check should narrow parameter to non-null"
		);
	}

	public function testEarlyReturnWithNullCheck() {
		$code = <<<'ENDCODE'
class MyClass {
	public ?string $name = null;
}

function getName(?MyClass $obj): string {
	if ($obj === null) {
		return "unknown";
	}
	// After the early return, $obj should be known to be non-null
	return $obj->name ?? "empty";
}
ENDCODE;
		
		$output = $this->analyzeStringToOutput("test.php", $code, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath"=>"/"]);
		
		// Should have 0 null access errors
		$this->assertEquals(0, $output->getErrorCount(), 
			"Early return with null check should narrow parameter to non-null"
		);
	}

	public function testMultipleEarlyReturns() {
		$code = <<<'ENDCODE'
class MyClass {
	public string $name = "";
}

function process(?MyClass $a, ?MyClass $b): string {
	if (!$a) {
		return "no-a";
	}
	if (!$b) {
		return "no-b";
	}
	// Both $a and $b should be known to be non-null here
	return $a->name . $b->name;
}
ENDCODE;
		
		$output = $this->analyzeStringToOutput("test.php", $code, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath"=>"/"]);
		
		// Should have 0 null access errors
		$this->assertEquals(0, $output->getErrorCount(), 
			"Multiple early returns should narrow all checked parameters"
		);
	}

	public function testOriginalBugCase() {
		$code = <<<'ENDCODE'
class ClassLike {
	public ?string $namespacedName = null;
}

function relativeClassName(?ClassLike $inside) {
	if (!$inside) {
		return "";
	}
	return $inside->namespacedName;
}
ENDCODE;
		
		$output = $this->analyzeStringToOutput("test.php", $code, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath"=>"/"]);
		
		// Should have 0 errors - $inside is known to be non-null after the early return
		$this->assertEquals(0, $output->getErrorCount(), 
			"Original bug case: early return should narrow nullable parameter to non-null"
		);
	}
}
