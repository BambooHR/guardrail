<?php

namespace BambooHR\Guardrail\Tests\units\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Class TestMethodCallCheck
 *
 * @package BambooHR\Guardrail\Tests\units\Checks
 */
class TestMethodCallCheck extends TestSuiteSetup {
	/**
	 * Test an unknown method check on a class with a method_exists() check prior to calling the method. See TestMethodCallCheck.1.inc for the example class.
	 * If method_exists() is called before invoking a method on an object, an ErrorConstants::TYPE_UNKNOWN_METHOD shouldn't be omitted.
	 *
	 * @return void
	 */
	public function testUnknownMethodWithExistsCheck(): void {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_UNKNOWN_METHOD));
	}

	/**
	 * Test that using method_exists doesn't break guardrail. See TestMethodCallCheck.2.inc for the example class.
	 *
	 * @return void
	 */
	public function testMethodExistsDoesntBreak(): void {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.2.inc', ErrorConstants::TYPE_UNKNOWN_METHOD));
	}

	public function testKnownMethodAfterInferringType(): void {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.3.inc', ErrorConstants::TYPE_UNKNOWN_METHOD));
	}

	/**
	 * Test that inline @var annotations work correctly with method chaining
	 * Tests both doc comment formats: standard and reversed order
	 * Note: Regular block comments are not supported by PHP parser's getDocComment()
	 *
	 * @return void
	 */
	public function testInlineVarAnnotations(): void {
		// Expecting 0 errors: both doc comment formats should work
		$this->assertEquals(0, $this->runAnalyzerOnFile('.inline-var.inc', ErrorConstants::TYPE_UNKNOWN_METHOD));
	}

	/**
	 * Test that inline @var annotations work correctly with method chaining
	 * Tests both doc comment formats: standard and reversed order
	 * Note: Regular block comments are not supported by PHP parser's getDocComment()
	 *
	 * @return void
	 */
	public function testInlineVarAnnotationsError(): void {
		// Expecting 4 errors: doc comment formats should not work with incorrect type
		$this->assertEquals(4, $this->runAnalyzerOnFile('.inline-var-error.inc', ErrorConstants::TYPE_UNKNOWN_METHOD));
	}
}
