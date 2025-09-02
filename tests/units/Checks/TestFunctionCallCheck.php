<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;
use BambooHR\Guardrail\CommandLineRunner;


/**
 * Class TestFunctionCalCheck
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class TestFunctionCallCheck extends TestSuiteSetup {

	/**
	 * testFunctionInFunctionCheck
	 *
	 * @return void
	 * @rapid-unit Checks:FunctionCallCheck:Catches all dangerous method calls and emits errors
	 */
	public function testDangerousFunctionsEmitErrors(): void {
		$this->assertEquals(8, $this->runAnalyzerOnFile('.1.inc',ErrorConstants::TYPE_SECURITY_DANGEROUS));
	}

	/**
	 * testFunctionCallWithTooManyArgs
	 *
	 * @return void
	 * @rapid-unit Checks:FunctionCallCheck:Catches a function call without enough arguments
	 */
	public function testFunctionCallWithTooManyArgs(): void {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.2.inc', ErrorConstants::TYPE_SIGNATURE_COUNT));
	}

	/**
	 * testCheckForDebugBackTraces
	 *
	 * @return void
	 * @rapid-unit
	 */
	public function testCheckForDebugBackTraces(): void {
		$this->assertEquals(6, $this->runAnalyzerOnFile('.3.inc', ErrorConstants::TYPE_DEBUG));
	}

	/**
	 * testDeprecatedUserFunctionCall
	 *
	 * @return void
	 * @rapid-unit Checks:FunctionCallCheck:Catches a deprecated user function call and emits error
	 */
	public function testDeprecatedUserFunctionCall(): void {
		$this->assertEquals(3, $this->runAnalyzerOnFile('.4.inc', ErrorConstants::TYPE_DEPRECATED_USER));
	}



	/**
	 * testUnknownFunctionCall
	 *
	 * @return void
	 * @rapid-unit Checks:FunctionCallCheck:Catches a call to a missing function and emits error
	 */
	public function testUnknownFunctionCall(): void {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.5.inc', ErrorConstants::TYPE_UNKNOWN_FUNCTION));
	}

	/**
	 * testVariableFunctionName
	 *
	 * @return void
	 */
	public function testVariableFunctionName(): void {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.6.inc', ErrorConstants::TYPE_VARIABLE_FUNCTION_NAME));
	}

	/**
	 *
	 * @return void
	 */
	public function testTimeZones(): void {
		$this->assertEquals(3, $this->runAnalyzerOnFile('.7.inc', ErrorConstants::TYPE_UNSAFE_TIME_ZONE));
	}

	/**
	 * @return void
	 */
	public function testNamespaces(): void {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.8.inc', ErrorConstants::TYPE_UNKNOWN_FUNCTION));
	}

	/**
	 * @return void
	 */
	public function testUnionTypeHints(): void {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.9.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}

	/**
	 * @return void
	 * @rapid-unit Checks:FunctionCallCheck:Ensures that passing an object with a __toString method is allowed as a valid argument to a method or function with a string requirement
	 */
	public function testObjectWith__toStringMethod(): void {
		$this->assertEquals(2, $this->runAnalyzerOnFile('.10.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}
	/**
	 * Test that invalid regex patterns emit a Guardrail error instead of terminating
	 *
	 * @return void
	 * @rapid-unit Checks:FunctionCallCheck:Properly handles invalid regex patterns
	 */
	public function testInvalidRegexPattern(): void {
		// Explicitly override error handler to match CommandLineRunner error handler (have to do this with PHPUnit v9.x.x -- see https://docs.phpunit.de/en/10.5/error-handling.html for when we upgrade).
		set_error_handler([CommandLineRunner::class, 'handleErrors'],  CommandLineRunner::ERROR_MASK);

		$this->assertEquals(1, $this->runAnalyzerOnFile('.11.inc', ErrorConstants::TYPE_INCORRECT_REGEX));

		restore_error_handler();
	}
}