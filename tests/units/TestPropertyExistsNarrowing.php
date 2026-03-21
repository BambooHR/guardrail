<?php

namespace BambooHR\Guardrail\Tests;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Test type narrowing for property_exists() function
 */
class TestPropertyExistsNarrowing extends TestSuiteSetup {

	public function testPropertyExistsNarrowsVariableToNonNull() {
		$code = <<<'ENDCODE'
<?php

class MyClass {
	public string $name = "";
}

function test(?MyClass $obj) {
	if (property_exists($obj, "name")) {
		// $obj is not null here
		return $obj->name;
	}
	return null;
}
ENDCODE;
		
		$output = $this->analyzeStringToOutput("test.php", $code, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath"=>"/"]);
		
		// Should have 0 errors - property_exists() narrows $obj to non-null
		$this->assertEquals(0, $output->getErrorCount(), 
			"property_exists() should narrow variable to non-null in truthy branch"
		);
	}

	public function testPropertyExistsWithClassConstant() {
		$code = <<<'ENDCODE'
<?php

class MyClass {
	public string $name = "";
}

function test() {
	if (property_exists(MyClass::class, "name")) {
		// Class exists and has the property
		$obj = new MyClass();
		return $obj->name;
	}
	return null;
}
ENDCODE;
		
		$output = $this->analyzeStringToOutput("test.php", $code, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath"=>"/"]);
		
		// Should have 0 errors - property_exists() with class constant is valid
		$this->assertEquals(0, $output->getErrorCount(), 
			"property_exists() with ClassName::class should be valid"
		);
	}

	public function testPropertyExistsWithStringLiteral() {
		$code = <<<'ENDCODE'
<?php

class MyClass {
	public string $name = "";
}

function test() {
	if (property_exists("MyClass", "name")) {
		// Class exists and has the property
		$obj = new MyClass();
		return $obj->name;
	}
	return null;
}
ENDCODE;
		
		$output = $this->analyzeStringToOutput("test.php", $code, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath"=>"/"]);
		
		// Should have 0 errors - property_exists() with string literal is valid
		$this->assertEquals(0, $output->getErrorCount(), 
			"property_exists() with string literal class name should be valid"
		);
	}

	public function testPropertyExistsDoesNotNarrowInFalseBranch() {
		$code = <<<'ENDCODE'
<?php

class MyClass {
	public string $name = "";
}

function test(?MyClass $obj) {
	if (!property_exists($obj, "name")) {
		// $obj might still be null or might not have the property
		// This should error because $obj could be null
		return $obj->name;
	}
	return null;
}
ENDCODE;
		
		$output = $this->analyzeStringToOutput("test.php", $code, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath"=>"/"]);
		
		// Should have 1 error - property_exists() does not narrow in false branch
		$this->assertEquals(1, $output->getErrorCount(), 
			"property_exists() should NOT narrow variable in falsy branch"
		);
	}

	public function testPropertyExistsWithSymbolicVariable() {
		$code = <<<'ENDCODE'
<?php

class Address {
	public string $street = "";
}

class Person {
	public ?Address $address = null;
}

function test(?Person $person) {
	if (property_exists($person, "address")) {
		// $person is not null here
		if ($person->address !== null) {
			return $person->address->street;
		}
	}
	return null;
}
ENDCODE;
		
		$output = $this->analyzeStringToOutput("test.php", $code, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath"=>"/"]);
		
		// Should have 0 errors - property_exists() narrows $person to non-null
		$this->assertEquals(0, $output->getErrorCount(), 
			"property_exists() should narrow variable even with nested property access"
		);
	}

	public function testPropertyExistsInComplexCondition() {
		$code = <<<'ENDCODE'
<?php

class MyClass {
	public string $name = "";
}

function test(?MyClass $obj) {
	if ($obj !== null && property_exists($obj, "name")) {
		// Both conditions are true - $obj is definitely not null
		return $obj->name;
	}
	return null;
}
ENDCODE;
		
		$output = $this->analyzeStringToOutput("test.php", $code, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath"=>"/"]);
		
		// Should have 0 errors - both conditions narrow $obj to non-null
		$this->assertEquals(0, $output->getErrorCount(), 
			"property_exists() should work in complex boolean conditions"
		);
	}

	public function testPropertyExistsWithNonLiteralProperty() {
		$code = <<<'ENDCODE'
<?php

class MyClass {
	public string $name = "";
}

function test(?MyClass $obj, string $propName) {
	if (property_exists($obj, $propName)) {
		// property_exists() with non-literal property name should not narrow
		// because we can't verify the property at compile time
		return $obj->name;
	}
	return null;
}
ENDCODE;
		
		$output = $this->analyzeStringToOutput("test.php", $code, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath"=>"/"]);
		
		// Should have 1 error - property_exists() with non-literal property doesn't narrow
		$this->assertEquals(1, $output->getErrorCount(), 
			"property_exists() with non-literal property name should not narrow"
		);
	}

	public function testPropertyExistsWithoutSecondArgument() {
		$code = <<<'ENDCODE'
<?php

class MyClass {
	public string $name = "";
}

function test(?MyClass $obj) {
	// Invalid call - property_exists requires 2 arguments
	if (property_exists($obj)) {
		return $obj->name;
	}
	return null;
}
ENDCODE;
		
		$output = $this->analyzeStringToOutput("test.php", $code, ErrorConstants::TYPE_NULL_DEREFERENCE, ["basePath"=>"/"]);
		
		// Should have 1 error - invalid property_exists call doesn't narrow
		$this->assertEquals(1, $output->getErrorCount(), 
			"property_exists() without second argument should not narrow"
		);
	}
}
