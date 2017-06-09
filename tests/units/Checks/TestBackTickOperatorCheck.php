<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\BacktickOperatorCheck;
use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Class TestBackTickOperatorCheck
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class TestBackTickOperatorCheck extends TestSuiteSetup {

	/**
	 * testBackTicksThrowError
	 *
	 * @return void
	 * @rapid-unit Checks:BackTickOperator:Emits error when back ticks are found
	 */
	public function testBackTicksThrowError() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_SECURITY_BACKTICK));
	}

	/**
	 * testBackTicksNotThrownInComment
	 *
	 * @return void
	 * @rapid-unit Checks:BackTickOperator:Doesn't care about back ticks in comments
	 */
	public function testBackTicksNotThrownInComment() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.2.inc', ErrorConstants::TYPE_SECURITY_BACKTICK));
	}
}