<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class TestAccessProtection extends TestSuiteSetup {

	/**
	 *
	 * @return void
	 * @rapid-unit Checks:AccessViolation:Emits an error when a private member variable is access by a child class
	 */
	public function testPrivateAccessInChildClass() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_ACCESS_VIOLATION));
	}


	/**
	 *
	 * @return void
	 * @rapid-unit Checks:AccessViolation:Does not emit an error accessing a protected variable in a child class.
	 */
	public function testProtectedVariableInChildClass() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.2.inc', ErrorConstants::TYPE_ACCESS_VIOLATION));
	}

	/**
	 *
	 * @return void
	 * @rapid-unit Checks:AccessViolation:Does not emit an error when accessing a public member variable
	 */
	public function testPublicMemberVariable() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.3.inc', ErrorConstants::TYPE_ACCESS_VIOLATION));
	}

	/**
	 *
	 * @return void
	 * @rapid-unit Checks:AccessViolation:Does not emit an error when accessing a private member variable in the same class.
	 */
	public function testPrivateAccessInSameClass() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.4.inc', ErrorConstants::TYPE_ACCESS_VIOLATION));
	}

	/**
	 *
	 * @return void
	 * @rapid-unit Checks:AccessViolation:Emits an error when accessing a protected variable from an unrelated class.
	 */
	public function testProtectedMemberVariableInUnrelatedClass() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.5.inc', ErrorConstants::TYPE_ACCESS_VIOLATION));
	}
}
