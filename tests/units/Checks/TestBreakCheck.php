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
		$testFile = dirname(__FILE__) . '/TestData/' . basename(__FILE__, '.php') . '.1.inc';
		$this->assertEquals(1, $this->runAnalyzerOnFile($testFile, ErrorConstants::TYPE_BREAK_NUMBER));
	}

	/**
	 * testBreakCheckInForeachWithoutLoops
	 *
	 * @return void
	 * @rapid-unit Checks:BreakCheck:Breaks without a numeric value after them are ok
	 */
	public function testBreakCheckInForeachWithoutLoops() {
		$testFile = dirname(__FILE__) . '/TestData/' . basename(__FILE__, '.php') . '.2.inc';
		$this->assertEquals(0, $this->runAnalyzerOnFile($testFile, ErrorConstants::TYPE_BREAK_NUMBER));
	}

	/**
	 * testContinueCheckInForeach
	 *
	 * @return void
	 * @rapid-unit Checks:BreakCheck:Continues followed by a numeric value will emit an error
	 */
	public function testContinueCheckInForeach() {
		$testFile = dirname(__FILE__) . '/TestData/' . basename(__FILE__, '.php') . '.3.inc';
		$this->assertEquals(1, $this->runAnalyzerOnFile($testFile, ErrorConstants::TYPE_BREAK_NUMBER));
	}

	/**
	 * testContinueCheckInForeachWithoutLoops
	 *
	 * @return void
	 * @rapid-unit Checks:BreakCheck:Continues without a numeric value after them are ok
	 */
	public function testContinueCheckInForeachWithoutLoops() {
		$testFile = dirname(__FILE__) . '/TestData/' . basename(__FILE__, '.php') . '.4.inc';
		$this->assertEquals(0, $this->runAnalyzerOnFile($testFile, ErrorConstants::TYPE_BREAK_NUMBER));
	}
}