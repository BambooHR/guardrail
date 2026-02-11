<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Class TestGotoCheck
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class TestGotoCheck extends TestSuiteSetup {
	/**
	 * @return void
	 * @rapid-unit Checks:GotoCheck:Emits error when goto is used
	 */
	public function testGotoEmitsError(): void {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_GOTO));
	}
}
