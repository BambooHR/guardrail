<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Class TestParamsTypeCheck
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class TestParamsTypeCheck extends TestSuiteSetup {

	/**
	 * testFunctionInFunctionCallEmitsError
	 *
	 * @return void
	 * @rapid-unit Checks:FunctionCallCheck:Function calling another function emits errors
	 */
	public function testFunctionInFunctionCallEmitsError() {
		$testFile = dirname(__FILE__) . '/TestData/' . basename(__FILE__, '.php') . '.1.inc';
		$this->assertEquals(4, $this->runAnalyzerOnFile($testFile, ErrorConstants::TYPE_FUNCTION_INSIDE_FUNCTION));
	}

}