<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;


/**
 * Class TestFunctionCalCheck
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class TestInstantionCheck extends TestSuiteSetup {

	/**
	 *
	 * @return void
	 */
	public function testUnsafeTimeZones() {
		$this->assertEquals(4, $this->runAnalyzerOnFile('.1.inc',ErrorConstants::TYPE_UNSAFE_TIME_ZONE));
	}
}