<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Class TestNamedParameters
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class TestNamedParameters extends TestSuiteSetup {

	/**
	 * testBadCalls
	 *
	 * @return void
	 */
	public function testBadCalls() {
		$this->assertEquals(3, $this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}
}