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

	/**
	 * Test a parent/child inheritance scheme where the child has a public method and the parent has a private method. The methods have
	 * different signatures. This is valid because the parent is private.
	 *
	 * @return void
	 */
	public function testPublicChildPrivateParentMethodWithDifferentSignaturesInheritance() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.4.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}

	/**
	 * Test a parent/child inheritance scheme where the child has a private method and the parent has a public method. The methods have
	 * different signatures. This is invalid because the parent is private but also because the parameters mismatch. Thus we should see 2 errors.
	 *
	 * @return void
	 */
	public function testPrivateChildPublicParentMethodWithDifferentSignaturesInheritance() {
		$this->assertEquals(2, $this->runAnalyzerOnFile('.5.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}

	/**
	 * Test 3 levels of inheritance where each child gets more visible than the parent. Parent => private, child1 => protected, child2 => public
	 * and it is valid to do so as long as the signature stays the same.
	 *
	 * @return void
	 */
	public function testSameSignatureDifferentVisibilityValid() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.6.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}

	/**
	 * Test that parameter type can be less specific in a child method, than that of its parent
	 *
	 * @return void
	 */
	public function testMethodParameterContravariance() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.7.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}

	/**
	 * A class that implements another is ok to have a type not defined when the parent type is mixed and the child class
	 * is ok to have a mixed type when the parent type is not defined. (mixed and not defined behave the same)
	 *
	 * @return void
	 */
	public function testMixedAndNoTypeAreTheSame() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.8.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}

	/**
	 * A child method should be able to return a more specific type than the return type of its parent method.
	 *
	 * @return void
	 */
	public function testCovarianceOfReturnTypes() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.9.inc', ErrorConstants::TYPE_SIGNATURE_RETURN));
	}
}