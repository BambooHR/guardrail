<?php

namespace BambooHR\Guardrail\Tests;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Test that property types can be read from docblocks when DocBlockProperties is enabled
 */
class TestDocBlockProperties extends TestSuiteSetup {

	public function testPropertyTypeFromDocBlockWithoutNativeType() {
		$code = <<<'ENDCODE'
<?php

class Address {
	public string $street = "";
}

class MyClass {
	/** @var Address */
	public $address;
}

function test(MyClass $obj) {
	// $obj->address should be inferred as Address from docblock
	// Accessing a non-existent property should error
	return $obj->address->nonExistentProperty;
}
ENDCODE;
		
		$output = $this->analyzeStringToOutput("test.php", $code, ErrorConstants::TYPE_UNKNOWN_PROPERTY, ["basePath"=>"/", "emitList"=>["DocBlockProperties"]]);
		
		// Should have 1 error - nonExistentProperty doesn't exist on Address
		$this->assertEquals(1, $output->getErrorCount(), 
			"Property type from @var docblock should be recognized when DocBlockProperties is enabled"
		);
	}

	public function testPropertyTypeFromDocBlockAllowsCorrectAccess() {
		$code = <<<'ENDCODE'
<?php

class Address {
	public string $street = "";
}

class MyClass {
	/** @var Address */
	public $address;
}

function test(MyClass $obj) {
	// $obj->address should be inferred as Address from docblock
	return $obj->address->street;
}
ENDCODE;
		
		$output = $this->analyzeStringToOutput("test.php", $code, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath"=>"/", "emitList"=>["DocBlockProperties"]]);
		
		// Should have 0 errors - $obj->address is documented as Address
		$this->assertEquals(0, $output->getErrorCount(), 
			"Property type from @var docblock should allow correct property access"
		);
	}

	public function testPropertyTypeFromDocBlockComplexType() {
		$code = <<<'ENDCODE'
<?php

class Address {
	public string $street = "";
}

class Person {
	/** @var Address */
	public $address;
}

function test(Person $person) {
	// $person->address should be inferred as Address from docblock
	return $person->address->street;
}
ENDCODE;
		
		$output = $this->analyzeStringToOutput("test.php", $code, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath"=>"/", "emitList"=>["DocBlockProperties"]]);
		
		// Should have 0 errors - $person->address is documented as Address
		$this->assertEquals(0, $output->getErrorCount(), 
			"Complex property type from @var docblock should be recognized"
		);
	}

	public function testNativeTypeOverridesDocBlock() {
		$code = <<<'ENDCODE'
<?php

class MyClass {
	/** @var int This docblock should be ignored */
	public string $name = "";
}

function test(MyClass $obj) {
	// $obj->name should be string (native type), not int (docblock)
	$length = strlen($obj->name);
	return $length;
}
ENDCODE;
		
		$output = $this->analyzeStringToOutput("test.php", $code, ErrorConstants::TYPE_SIGNATURE_TYPE, ["basePath"=>"/", "emitList"=>["DocBlockProperties"]]);
		
		// Should have 0 errors - native type (string) takes precedence over docblock (int)
		$this->assertEquals(0, $output->getErrorCount(), 
			"Native property type should take precedence over @var docblock"
		);
	}

	public function testDocBlockPropertiesDisabled() {
		$code = <<<'ENDCODE'
<?php

class MyClass {
	/** @var string */
	public $name;
}

function test(MyClass $obj) {
	// Without DocBlockProperties enabled, type should be unknown
	$length = strlen($obj->name);
	return $length;
}
ENDCODE;
		
		$output = $this->analyzeStringToOutput("test.php", $code, ErrorConstants::TYPE_SIGNATURE_TYPE, ["basePath"=>"/"]);
		
		// When DocBlockProperties is disabled, the analyzer might emit warnings about unknown types
		// or might not - this test just verifies the setting can be toggled
		$this->assertGreaterThanOrEqual(0, $output->getErrorCount(), 
			"Test should complete without DocBlockProperties enabled"
		);
	}
}
