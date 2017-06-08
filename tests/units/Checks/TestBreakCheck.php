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
	 * @rapid-unit Checks:BreakCheck:Any break followed by a number will emit error
	 */
	public function testBreakCheckInForeach() {
		$testFile = dirname(__FILE__) . '/TestData/' . basename(__FILE__, '.php') . '.1.inc';
		$this->assertEquals(1, $this->runAnalyzerOnFile($testFile, ErrorConstants::TYPE_BREAK_NUMBER));
	}

	/**
	 * testBreakCheckInForeachWithoutLoops
	 *
	 * @return void
	 * @rapid-unit Checks:BreakCheck:Any break without a number will pass
	 */
	public function testBreakCheckInForeachWithoutLoops() {
		$testFile = dirname(__FILE__) . '/TestData/' . basename(__FILE__, '.php') . '.2.inc';
		$this->assertEquals(0, $this->runAnalyzerOnFile($testFile, ErrorConstants::TYPE_BREAK_NUMBER));
	}

	/**
	 * testContinueCheckInForeach
	 *
	 * @return void
	 * @rapid-unit Checks:BreakCheck:Any continue followed by a number will emit error
	 */
	public function testContinueCheckInForeach() {
		$testFile = dirname(__FILE__) . '/TestData/' . basename(__FILE__, '.php') . '.3.inc';
		$this->assertEquals(1, $this->runAnalyzerOnFile($testFile, ErrorConstants::TYPE_BREAK_NUMBER));
	}

	/**
	 * testContinueCheckInForeachWithoutLoops
	 *
	 * @return void
	 * @rapid-unit Checks:BreakCheck:Any continue without a number will pass
	 */
	public function testContinueCheckInForeachWithoutLoops() {
		$testFile = dirname(__FILE__) . '/TestData/' . basename(__FILE__, '.php') . '.4.inc';
		$this->assertEquals(0, $this->runAnalyzerOnFile($testFile, ErrorConstants::TYPE_BREAK_NUMBER));
	}
}