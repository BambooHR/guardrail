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
		$this->assertEquals(1, $this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_UNKNOWN_CLASS));
	}

	/**
	 * testBaseExceptionCatch
	 *
	 * @return void
	 * @rapid-unit Checks:CatchCheck:Emits error if the catch contains an exception that is considered too broad
	 */
	public function testBaseExceptionCatch() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.2.inc', ErrorConstants::TYPE_EXCEPTION_BASE));
	}

	/**
	 * @rapid-unit Checks:CatchCheck:Does not emit error if the catch block explicitly throws an exception.
	 */
	public function testRethrowBaseException() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.3.inc', ErrorConstants::TYPE_EXCEPTION_BASE));
	}

	public function testNoErrorExistingException() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.4.inc', ErrorConstants::TYPE_UNKNOWN_CLASS));
	}

	public function testNoErrorNotBaseException() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.5.inc', ErrorConstants::TYPE_EXCEPTION_BASE));
	}

	public function testNonDerivedThrowable() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.6.inc', ErrorConstants::TYPE_UNKNOWN_CLASS));
	}

	public function testThrowable() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.7.inc', ErrorConstants::TYPE_UNKNOWN_CLASS));
	}

	/**
	 * @rapid-unit Checks:CatchCheck:Does not emit error if the catch block explicitly throws an exception.
	 */
	public function testRethrowStopTraversal() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.8.inc', ErrorConstants::TYPE_EXCEPTION_BASE));
	}

}