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
	 * define() constants are typed as mixed. strict_types does not reject
	 * mixed, and literal arguments of the declared types are accepted.
	 *
	 * @return void
	 */
	public function testDefineConstants() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.define-consts-pass.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}

	/**
	 * User-defined constants are typed as mixed. strict_types does not reject
	 * mixed, so these calls produce no signature errors. nullTest(null)
	 * receives the literal null, which is typed as null.
	 *
	 * @return void
	 */
	public function testBasicGlobalConstantTypes() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.basic-types-pass.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}

	/**
	 * User-defined constants are typed as mixed, including ones defined with
	 * bitwise expressions. strict_types does not reject mixed, so these calls
	 * produce no signature errors.
	 *
	 * @return void
	 */
	public function testBitwiseOperationsInGlobalConstants() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.bitwise-ops-pass.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}

	/**
	 * User-defined constants are typed as mixed, including a negated int.
	 * strict_types does not reject mixed, so the call produces no signature error.
	 *
	 * @return void
	 */
	public function testNegativeValuesInGlobalConstants() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.negative-values-pass.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}

	/**
	 * A constant defined in Guardrail's process, rather than by PHP, is not
	 * given that process value's type. An int constant passed to string stays
	 * mixed and is not a signature error.
	 *
	 * @return void
	 */
	public function testProcessUserConstantStaysMixed() {
		if (!defined('GUARDRAIL_PROCESS_ONLY_CONST')) {
			define('GUARDRAIL_PROCESS_ONLY_CONST', 1);
		}
		$code = <<<'ENDCODE'
			declare(strict_types=1);
			function takeString(string $value): void {
			}
			takeString(GUARDRAIL_PROCESS_ONLY_CONST);
		ENDCODE;
		$counts = $this->analyzeString($code)->getCounts();
		$this->assertEquals(0, $counts[ErrorConstants::TYPE_SIGNATURE_TYPE] ?? 0);
	}

	/**
	 * namespace App; const E_ERROR = 'fatal' hides the global int E_ERROR.
	 * takesString(E_ERROR) uses the namespaced constant and is not a signature error.
	 * Without that const, the same call falls back to the global int.
	 *
	 * @return void
	 */
	public function testNamespacedConstantShadowsGlobalConstant() {
		$shadowed = <<<'ENDCODE'
			declare(strict_types=1);
			namespace App;
			const E_ERROR = 'fatal';
			function takesString(string $value): void {
			}
			takesString(E_ERROR);
		ENDCODE;
		$counts = $this->analyzeString($shadowed)->getCounts();
		$this->assertEquals(0, $counts[ErrorConstants::TYPE_SIGNATURE_TYPE] ?? 0);

		$fallback = <<<'ENDCODE'
			declare(strict_types=1);
			namespace App;
			function takesString(string $value): void {
			}
			takesString(E_ERROR);
		ENDCODE;
		$counts = $this->analyzeString($fallback)->getCounts();
		$this->assertEquals(1, $counts[ErrorConstants::TYPE_SIGNATURE_TYPE] ?? 0);
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
