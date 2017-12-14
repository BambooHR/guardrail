<?php

namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Class TestInterfaceCheck
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class TestInterfaceCheck extends TestSuiteSetup {
	/**
	 * Test a parent/child inheritance scheme where the child and parent both have a private method with the same name but
	 * different signatures. This is valid.
	 *
	 * @return void
	 */
	public function testPrivateMethodsWithDifferentSignaturesInheritance() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}

	/**
	 * Test a parent/child inheritance scheme where the child and parent both have a protected method with the same name but
	 * different signatures. This is invalid.
	 *
	 * @return void
	 */
	public function testProtectedMethodsWithDifferentSignaturesInheritance() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.2.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}

	/**
	 * Test a parent/child inheritance scheme where the child and parent both have a public method with the same name but
	 * different signatures. This is invalid.
	 *
	 * @return void
	 */
	public function testPublicMethodsWithDifferentSignaturesInheritance() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.3.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}
}