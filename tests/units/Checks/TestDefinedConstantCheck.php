<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Class TestDefinedConstantCheck
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class TestDefinedConstantCheck extends TestSuiteSetup {

	/**
	 * testUndefinedGlobalConstant
	 *
	 * @return void
	 */
	public function testUndefinedGlobalConstant() {
		$this->assertEquals(2, $this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_UNKNOWN_GLOBAL_CONSTANT));
	}
}