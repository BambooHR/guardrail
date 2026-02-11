<?php

namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Test that ConstFetch evaluator correctly infers types for runtime PHP constants
 * like PHP_VERSION, FILTER_VALIDATE_EMAIL, true, false, null, etc.
 */
class TestConstFetch extends TestSuiteSetup
{
	/**
	 * Test that runtime PHP constants are correctly typed
	 *
	 * @return void
	 */
	public function testRuntimeConstantTypes() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.php-consts.inc', ''));
	}

	/**
	 * Test that true, false, and null are correctly typed
	 *
	 * @return void
	 */
	public function testBooleanAndNullConstants() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.bool-null.inc', ''));
	}

	/**
	 * Test that define() constants are correctly typed
	 *
	 * @return void
	 */
	public function testDefineConstants() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.define-consts.inc', ''));
	}

	/**
	 * Test basic global constant types
	 *
	 * @return void
	 */
	public function testBasicGlobalConstantTypes() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.basic-types.inc', ''));
	}

	/**
	 * Test bitwise operations in global constants
	 *
	 * @return void
	 */
	public function testBitwiseOperationsInGlobalConstants() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.bitwise-ops.inc', ''));
	}

	/**
	 * Test negative values in global constants
	 *
	 * @return void
	 */
	public function testNegativeValuesInGlobalConstants() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.negative-values.inc', ''));
	}

	/**
	 * Test that PHP runtime constants are correctly typed as int in strict mode
	 *
	 * This test verifies that PHP runtime constants (FILE_APPEND, JSON_PRETTY_PRINT,
	 * FILTER_VALIDATE_EMAIL, etc.) are properly typed based on their actual values
	 * instead of being typed as 'mixed'.
	 *
	 * The fix: ConstFetch::getType() now uses constant() to get the actual value
	 * and infers the type from that value using getTypeFromValue().
	 *
	 * @return void
	 */
	public function testPhpConstantsInStrictMode() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.php-consts.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}
}
