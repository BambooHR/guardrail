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
		$this->assertEquals(21, $this->runAnalyzerOnFile('.3.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}

	public function testParamTypeDefineNoError() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.4.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}

	public function testParamTypeDefineErrors() {
		$this->assertEquals(10, $this->runAnalyzerOnFile('.5.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}

	public function testParamTypeClassConstantsNoError() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.6.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}

	public function testParamTypeClassConstantsErrors() {
		$this->assertEquals(12, $this->runAnalyzerOnFile('.7.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}

	public function testParamTypeBitWiseNoError() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.8.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}

	public function testParamTypeBitWiseErrors() {
		$this->assertEquals(14, $this->runAnalyzerOnFile('.9.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}

	public function testParamTypeNegativeNoError() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.10.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}

	public function testParamTypeCrossClassConstantsNoError() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.11.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}
}
