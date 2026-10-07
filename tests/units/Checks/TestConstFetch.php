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
		$this->assertEquals(0, $this->runAnalyzerOnFile('.php-consts-pass.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}

	/**
	 * Test that true, false, and null are correctly typed
	 *
	 * @return void
	 */
	public function testBooleanAndNullConstants() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.bool-null-pass.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}

	public function testBooleanAndNullConstantsFail() {
		$this->assertEquals(4, $this->runAnalyzerOnFile('.bool-null-fail.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}

	/**
	 * Test that define() constants are correctly typed
	 *
	 * @return void
	 */
	public function testDefineConstants() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.define-consts-pass.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}

	/**
	 * Constants created with define() are typed as mixed. The value passed to
	 * define() is never read. With strict_types=1, mixed is not compatible
	 * with a narrower parameter, so each argument is a signature error even
	 * when the defined value would fit.
	 *
	 * @return void
	 */
	public function testDefineConstantsFail() {
		$this->assertEquals(6, $this->runAnalyzerOnFile('.define-consts-fail.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}

	/**
	 * User-defined constants are typed as mixed. With strict_types=0, a mixed
	 * argument is compatible with any parameter, so these calls produce no
	 * signature errors. nullTest receives the literal null, which is typed as null.
	 *
	 * @return void
	 */
	public function testBasicGlobalConstantTypes() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.basic-types-pass.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}

	/**
	 * User-defined constants are typed as mixed. With strict_types=1, mixed is
	 * not compatible with a narrower parameter, so each call is a signature
	 * error even when the constant's value would fit:
	 *
	 * @return void
	 */
	public function testBasicGlobalConstantTypesFail() {
		$this->assertEquals(8, $this->runAnalyzerOnFile('.basic-types-fail.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}

	/**
	 * User-defined constants are typed as mixed, including ones defined with
	 * bitwise expressions. With strict_types=0, a mixed argument is compatible
	 * with any parameter, so these calls produce no signature errors.
	 *
	 * @return void
	 */
	public function testBitwiseOperationsInGlobalConstants() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.bitwise-ops-pass.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}

	/**
	 * User-defined constants are typed as mixed, including ones defined with
	 * bitwise expressions. With strict_types=1, mixed is not compatible with
	 * int, so each call is a signature error even though every expression
	 * evaluates to an int.
	 *
	 * @return void
	 */
	public function testBitwiseOperationsInGlobalConstantsFail() {
		$this->assertEquals(6, $this->runAnalyzerOnFile('.bitwise-ops-fail.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}

	/**
	 * User-defined constants are typed as mixed, including a negated int.
	 * With strict_types=0, a mixed argument is compatible with int.
	 *
	 * @return void
	 */
	public function testNegativeValuesInGlobalConstants() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.negative-values-pass.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}

	/**
	 * User-defined constants are typed as mixed, including a negated int.
	 * With strict_types=1, mixed is not compatible with int.
	 *
	 * @return void
	 */
	public function testNegativeValuesInGlobalConstantsFail() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.negative-values-fail.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}

	/**
	 * Runtime PHP constants are typed from their current value instead of mixed.
	 * The pass fixture pairs each constant with a matching parameter.
	 * The fail fixture is strict_types=1 and passes each constant to a parameter
	 * of a different type, so all 10 calls are signature errors:
	 * PHP_VERSION (string) to int, float, bool, and null; M_PI (float) to int
	 * and string; PHP_INT_MAX (int) to string and bool; TRUE (true) to float;
	 * FALSE (false) to null.
	 *
	 * @return void
	 */
	public function testPhpConstantsInStrictMode() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.php-consts-pass.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
		$this->assertEquals(10, $this->runAnalyzerOnFile('.php-consts-fail.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}
}
