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
	 * - both doc comment formats (/* and /**) should work
	 * - both reversed and standard order should work
	 *
	 * @return void
	 */
	public function testInlineVarAnnotations(): void {
		// Expecting 0 errors:
		$this->assertEquals(0, $this->runAnalyzerOnFile('.inline-var.inc', ErrorConstants::TYPE_UNKNOWN_METHOD));
	}

	/**
	 * Test that inline @var annotations work correctly with method chaining
	 * - both doc comment formats (/* and /**) should not work with incorrect type
	 * - should error on standard comment (//)
	 * - both reversed and standard order should not work with incorrect type
	 *
	 * @return void
	 */
	public function testInlineVarAnnotationsError(): void {
		// Expecting 6 errors: doc comment formats should not work with incorrect type
		$this->assertEquals(6, $this->runAnalyzerOnFile('.inline-var-error.inc', ErrorConstants::TYPE_UNKNOWN_METHOD));
	}
}
