<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;


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
	public function testDangerousFunctionsEmitErrors() {
		$this->assertEquals(8, $this->runAnalyzerOnFile('.1.inc',ErrorConstants::TYPE_SECURITY_DANGEROUS));
	}

	/**
	 * testFunctionCallWithTooManyArgs
	 *
	 * @return void
	 * @rapid-unit Checks:FunctionCallCheck:Catches a function call without enough arguments
	 */
	public function testFunctionCallWithTooManyArgs() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.2.inc', ErrorConstants::TYPE_SIGNATURE_COUNT));
	}

	/**
	 * testCheckForDebugBackTraces
	 *
	 * @return void
	 * @rapid-unit
	 */
	public function testCheckForDebugBackTraces() {
		$this->assertEquals(6, $this->runAnalyzerOnFile('.3.inc', ErrorConstants::TYPE_DEBUG));
	}

	/**
	 * testDeprecatedUserFunctionCall
	 *
	 * @return void
	 * @rapid-unit Checks:FunctionCallCheck:Catches a deprecated user function call and emits error
	 */
	public function testDeprecatedUserFunctionCall() {
		$this->assertEquals(3, $this->runAnalyzerOnFile('.4.inc', ErrorConstants::TYPE_DEPRECATED_USER));
	}



	/**
	 * testUnknownFunctionCall
	 *
	 * @return void
	 * @rapid-unit Checks:FunctionCallCheck:Catches a call to a missing function and emits error
	 */
	public function testUnknownFunctionCall() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.5.inc', ErrorConstants::TYPE_UNKNOWN_FUNCTION));
	}

	/**
	 * testVariableFunctionName
	 *
	 * @return void
	 */
	public function testVariableFunctionName() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.6.inc', ErrorConstants::TYPE_VARIABLE_FUNCTION_NAME));
	}

	/**
	 *
	 * @return void
	 */
	public function testTimeZones() {
		$this->assertEquals(3, $this->runAnalyzerOnFile('.7.inc', ErrorConstants::TYPE_UNSAFE_TIME_ZONE));
	}

	/**
	 * @return void
	 */
	public function testNamespaces() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.8.inc', ErrorConstants::TYPE_UNKNOWN_FUNCTION));
	}
}