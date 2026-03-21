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

	/**
	 * Test that chained method calls with docblock return types work correctly
	 * When a method returns a type specified in a docblock, and the result is assigned
	 * to a variable, subsequent method calls on that variable should use the inferred type.
	 *
	 * @return void
	 */
	public function testChainedMethodCallsWithDocblockReturnTypes(): void {
		// Expecting 0 errors: all method calls should resolve correctly
		$this->assertEquals(0, $this->runAnalyzerOnFile('.chained-docblock.inc', ErrorConstants::TYPE_UNKNOWN_METHOD));
	}

	/**
	 * Test the exact scenario: $var=(new Foo())->chainableFoo(); $var->chainableFoo();
	 * where chainableFoo()'s return type is specified in a docblock.
	 *
	 * @return void
	 */
	public function testChainedMethodCallExactScenario(): void {
		// Expecting 0 errors: the type should be inferred from the docblock
		$this->assertEquals(0, $this->runAnalyzerOnFile('.chained-specific.inc', ErrorConstants::TYPE_UNKNOWN_METHOD));
	}

	/**
	 * Test that @return $this in docblocks works correctly for fluent interfaces
	 *
	 * @return void
	 */
	public function testReturnThisDocblock(): void {
		// Expecting 0 errors: @return $this should infer the class type
		$this->assertEquals(0, $this->runAnalyzerOnFile('.return-this.inc', ErrorConstants::TYPE_UNKNOWN_METHOD));
	}
}
