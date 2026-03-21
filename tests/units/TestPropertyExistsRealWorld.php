<?php

namespace BambooHR\Guardrail\Tests;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Test property_exists() type narrowing with real-world patterns
 */
class TestPropertyExistsRealWorld extends TestSuiteSetup {

	public function testPropertyExistsPatternFromUtilPhp() {
		// This is the pattern from Util.php line 49 that was reported as a false positive
		$code = <<<'ENDCODE'
<?php

class Parts {
	public array $parts = [];
}

function finalPart($parts) {
	return property_exists($parts, "parts") && is_array($parts->parts) 
		? $parts->parts[count($parts->parts) - 1] 
		: $parts;
}

function test() {
	$obj = new Parts();
	$obj->parts = ["a", "b", "c"];
	return finalPart($obj);
}
ENDCODE;
		
		$output = $this->analyzeStringToOutput("test.php", $code, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath"=>"/"]);
		
		// Should have 0 errors - property_exists() narrows $parts to non-null before accessing ->parts
		$this->assertEquals(0, $output->getErrorCount(), 
			"property_exists() should prevent false positive null dereference warnings"
		);
	}

	public function testPropertyExistsWithAndCondition() {
		$code = <<<'ENDCODE'
<?php

class Config {
	public ?array $settings = null;
}

function getSetting(?Config $config, string $key) {
	if (property_exists($config, "settings") && is_array($config->settings)) {
		return $config->settings[$key] ?? null;
	}
	return null;
}
ENDCODE;
		
		$output = $this->analyzeStringToOutput("test.php", $code, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath"=>"/"]);
		
		// Should have 0 errors - property_exists() narrows $config to non-null
		$this->assertEquals(0, $output->getErrorCount(), 
			"property_exists() in AND condition should narrow variable"
		);
	}

	public function testPropertyExistsDoesNotPreventOtherErrors() {
		$code = <<<'ENDCODE'
<?php

class MyClass {
	public string $name = "";
}

function test(?MyClass $obj) {
	if (property_exists($obj, "name")) {
		// $obj is not null, but accessing non-existent property should still error
		return $obj->nonExistentProperty;
	}
	return null;
}
ENDCODE;
		
		$output = $this->analyzeStringToOutput("test.php", $code, ErrorConstants::TYPE_UNKNOWN_PROPERTY, ["basePath"=>"/"]);
		
		// Should have 1 error - property_exists() doesn't prevent unknown property errors
		$this->assertEquals(1, $output->getErrorCount(), 
			"property_exists() should not suppress other property access errors"
		);
	}

	public function testPropertyExistsWithPropertyFetch() {
		$code = <<<'ENDCODE'
<?php

class Inner {
	public string $value = "";
}

class Outer {
	public ?Inner $inner = null;
}

function test(?Outer $obj) {
	if (property_exists($obj, "inner") && $obj->inner !== null) {
		return $obj->inner->value;
	}
	return null;
}
ENDCODE;
		
		$output = $this->analyzeStringToOutput("test.php", $code, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath"=>"/"]);
		
		// Should have 0 errors - property_exists() narrows $obj, then explicit null check narrows $obj->inner
		$this->assertEquals(0, $output->getErrorCount(), 
			"property_exists() should work with subsequent property access checks"
		);
	}
}
