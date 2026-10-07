<?php namespace BambooHR\Guardrail\Test\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Class TestDefinedConstantCheck
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class TestEnumCheck extends TestSuiteSetup {

	public function testInheritance() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.2.inc', ErrorConstants::TYPE_ILLEGAL_ENUM), "Failed to detect inheriting from an enum" );	}

	public function testProperty() {

		$func = <<<'ENDCODE'
			enum Foo {
				case Bar;
				case Baz;
				public $foo;
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_ILLEGAL_ENUM, ["basePath" => "/"]);
		$this->assertEquals(1, $output->getErrorCount(), "Failed to detect declaring a property in an enum");

	}

	public function testTraitPropertyFailure() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_ILLEGAL_ENUM), "Failed to detect traits with properties" );
	}


	public function testIncompatibleTypes() {
		$func = <<<'ENDCODE'
			enum Foo:string {
				case Bar="hello";
				case Baz=2;
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_ILLEGAL_ENUM, ["basePath" => "/"]);
		$this->assertEquals(1, $output->getErrorCount(), "Failed to detect declaring a property in an enum");
	}

	public function testEmptyBackedTypes() {
		$func = <<<'ENDCODE'
			enum Foo:string {
				case Bar;
				case Baz;
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_ILLEGAL_ENUM, ["basePath" => "/"]);
		$this->assertEquals(2, $output->getErrorCount(), "Failed to detect declaring a property in an enum");
	}

	public function testValuesForUnbackedTypes() {
		$func = <<<'ENDCODE'
			enum Foo {
				case Bar=1;
				case Baz="2";
			}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_ILLEGAL_ENUM, ["basePath" => "/"]);
		$this->assertEquals(2, $output->getErrorCount(), "Failed to detect declaring a property in an enum");
	}

	public function testBackedCallToFrom() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.4.inc', ErrorConstants::TYPE_UNKNOWN_METHOD), "Failed to find enum::from()" );
	}

	public function testLegalEnumUsage() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.3.inc', ErrorConstants::TYPE_ILLEGAL_ENUM), "Failed to detect traits with properties" );
	}

	public function testValuesTypeFetch() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.5.inc', ErrorConstants::TYPE_UNKNOWN_PROPERTY), "Failed retrieve the name of a specific enum case");
	}
}