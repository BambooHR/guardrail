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
		$testFile = dirname(__FILE__) . '/TestData/' . basename(__FILE__, '.php') . '.1.inc';
		$this->assertEquals(8, $this->runAnalyzerOnFile($testFile,ErrorConstants::TYPE_SECURITY_DANGEROUS));
	}

	/**
	 * testFunctionCallWithTooManyArgs
	 *
	 * @return void
	 * @rapid-unit Checks:FunctionCallCheck:Catches a function call without enough arguments
	 */
	public function testFunctionCallWithTooManyArgs() {
		$testFile = dirname(__FILE__) . '/TestData/' . basename(__FILE__, '.php') . '.2.inc';
		$this->assertEquals(1, $this->runAnalyzerOnFile($testFile, ErrorConstants::TYPE_SIGNATURE_COUNT));
	}

	/**
	 * testCheckForDebugBackTraces
	 *
	 * @return void
	 * @rapid-unit
	 */
	public function testCheckForDebugBackTraces() {
		$testFile = dirname(__FILE__) . '/TestData/' . basename(__FILE__, '.php') . '.3.inc';
		$this->assertEquals(6, $this->runAnalyzerOnFile($testFile, ErrorConstants::TYPE_DEBUG));
	}

	/**
	 * testDeprecatedUserFunctionCall
	 *
	 * @return void
	 * @rapid-unit Checks:FunctionCallCheck:Catches a deprecated user function call and emits error
	 */
	public function testDeprecatedUserFunctionCall() {
		$testFile = dirname(__FILE__) . '/TestData/' . basename(__FILE__, '.php') . '.4.inc';
		$this->assertEquals(3, $this->runAnalyzerOnFile($testFile, ErrorConstants::TYPE_DEPRECATED_USER));
	}

	/**
	 * testUnknownFunctionCall
	 *
	 * @return void
	 * @rapid-unit Checks:FunctionCallCheck:Catches a call to a missing function and emits error
	 */
	public function testUnknownFunctionCall() {
		$testFile = dirname(__FILE__) . '/TestData/' . basename(__FILE__, '.php') . '.5.inc';
		$this->assertEquals(1, $this->runAnalyzerOnFile($testFile, ErrorConstants::TYPE_UNKNOWN_FUNCTION));
	}

	/**
	 * testVariableFunctionName
	 *
	 * @return void
	 */
	public function testVariableFunctionName() {
		$testFile = dirname(__FILE__) . '/TestData/' . basename(__FILE__, '.php') . '.6.inc';
		$this->assertEquals(1, $this->runAnalyzerOnFile($testFile, ErrorConstants::TYPE_VARIABLE_FUNCTION_NAME));
	}
}