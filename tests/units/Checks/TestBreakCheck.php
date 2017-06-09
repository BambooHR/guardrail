<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\BreakCheck;
use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Class TestBreakCheck
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class TestBreakCheck extends TestSuiteSetup {

	/**
	 * testBreakCheckInForeach
	 *
	 * @return void
	 * @rapid-unit Checks:BreakCheck:Breaks followed by a numeric value will emit an error
	 */
	public function testBreakCheckInForeach() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_BREAK_NUMBER));
	}

	/**
	 * testBreakCheckInForeachWithoutLoops
	 *
	 * @return void
	 * @rapid-unit Checks:BreakCheck:Breaks without a numeric value after them are ok
	 */
	public function testBreakCheckInForeachWithoutLoops() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.2.inc', ErrorConstants::TYPE_BREAK_NUMBER));
	}

	/**
	 * testContinueCheckInForeach
	 *
	 * @return void
	 * @rapid-unit Checks:BreakCheck:Continues followed by a numeric value will emit an error
	 */
	public function testContinueCheckInForeach() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.3.inc', ErrorConstants::TYPE_BREAK_NUMBER));
	}

	/**
	 * testContinueCheckInForeachWithoutLoops
	 *
	 * @return void
	 * @rapid-unit Checks:BreakCheck:Continues without a numeric value after them are ok
	 */
	public function testContinueCheckInForeachWithoutLoops() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.4.inc', ErrorConstants::TYPE_BREAK_NUMBER));
	}
}