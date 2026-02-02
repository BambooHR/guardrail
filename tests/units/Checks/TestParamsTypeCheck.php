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
	 * @rapid-unit Checks:ParamsTypeCheck:Function calling another function emits errors
	 */
	public function testFunctionInFunctionCallEmitsError() {
		$this->assertEquals(4, $this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_FUNCTION_INSIDE_FUNCTION));
	}
	public function testParamTypeNoError() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.2.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}

	public function testParamTypeErrors() {
		$this->assertEquals(8, $this->runAnalyzerOnFile('.3.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}
}