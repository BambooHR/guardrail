<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\CatchCheck;
use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Class TestCatchCheck
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class TestCatchCheck extends TestSuiteSetup {

	/**
	 * testMissingExceptionClass
	 *
	 * @return void
	 * @rapid-unit Checks:CatchCheck:Emits error when unknown exception class is found
	 */
	public function testMissingExceptionClass() {
		$testFile = dirname(__FILE__) . '/TestData/' . basename(__FILE__, '.php') . '.1.inc';
		$this->assertEquals(1, $this->runAnalyzerOnFile($testFile, ErrorConstants::TYPE_UNKNOWN_CLASS));
	}

	/**
	 * testBaseExceptionCatch
	 *
	 * @return void
	 * @rapid-unit Checks:CatchCheck:Emits error if the catch contains an exception that is considered too broad
	 */
	public function testBaseExceptionCatch() {
		$testFile = dirname(__FILE__) . '/TestData/' . basename(__FILE__, '.php') . '.2.inc';
		$this->assertEquals(1, $this->runAnalyzerOnFile($testFile, ErrorConstants::TYPE_EXCEPTION_BASE));
	}
}