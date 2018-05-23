<?php

namespace BambooHR\Guardrail\Tests\units\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Class TestPropertyFetchCheck
 *
 * @package BambooHR\Guardrail\Tests\units\Checks
 */
class TestPropertyFetchCheck extends TestSuiteSetup {
	/**
	 * Test accessing declared private and protected members on an object without a __get() method. Guardrail should complain for both.
	 *
	 * @return void
	 * @rapid-unit Checks:PropertyFetchCheck:Cannot access private or protected member variables directly without __get() method
	 */
	public function testAccessingDeclaredPrivateAndProtectedMemberNo__get() {
		$this->assertEquals(2, $this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_ACCESS_VIOLATION));
	}

	/**
	 * Test accessing declared private and protected members on an object with a __get() method. Guardrail shouldn't have any problem.
	 *
	 * @return void
	 * @rapid-unit Checks:PropertyFetchCheck:Can access private and protected member variables directly with __get() method
	 */
	public function testAccessingDeclaredPrivateAndProtectedMemberYes__get() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.2.inc', ErrorConstants::TYPE_ACCESS_VIOLATION));
	}

	/**
	 * Test accessing declared private and protected members on objects with and without a __get() method in different stages of an object hierarchy.
	 * The parent doesn't have a __get() method so it should fail. The child does have a __get() so it should succeed. The grandchild doesn't have a
	 * __get() but its parent does so it should also be safe.
	 *
	 * @return void
	 * @rapid-unit Checks:PropertyFetchCheck:Cann access private member variable directly with __get() method in self or parent
	 */
	public function testAccessingDeclaredPrivateAndProtectedMemberHierarchy() {
		$this->assertEquals(2, $this->runAnalyzerOnFile('.3.inc', ErrorConstants::TYPE_ACCESS_VIOLATION));
	}

	/**
	 * Test accessing an undeclared member variable on an object without a __get() method.
	 *
	 * @return void
	 * @rapid-unit Checks:PropertyFetchCheck:Cannot access unknown property on object without a __get() method
	 */
	public function testAccessingUndeclaredMemberVariableNo__get() {
		$this->assertEquals(2, $this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_UNKNOWN_PROPERTY));
	}

	/**
	 * Test accessing an undeclared member variable on an object with a __get() method.
	 *
	 * @return void
	 * @rapid-unit Checks:PropertyFetchCheck:Can access unknown property on object with a __get() method
	 */
	public function testAccessingUndeclaredMemberVariableYes__get() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.2.inc', ErrorConstants::TYPE_UNKNOWN_PROPERTY));
	}

	/**
	 * Test accessing undeclared member variables on objects with and without a __get() method in different stages of an object hierarchy.
	 * The parent doesn't have a __get() method so it should fail. The child does have a __get() so it should succeed. The grandchild doesn't
	 * have a __get() but its parent does so it should also be safe.
	 *
	 * @return void
	 * @rapid-unit Checks:PropertyFetchCheck:Can access unknown property on object with a __get() method in self or parent
	 */
	public function testAccessingUndeclaredMemberVariableHierarchy() {
		$this->assertEquals(2, $this->runAnalyzerOnFile('.3.inc', ErrorConstants::TYPE_UNKNOWN_PROPERTY));
	}

	/**
	 * Test accessing an undeclared member variable with the same name as a method on an object without a __get() method.
	 *
	 * @return void
	 * @rapid-unit Checks:PropertyFetchCheck:Cannot access unknown property with same name as method on object without a __get() method
	 */
	public function testAccessingUndeclaredMemberVariableSameNameAsMethodNo__get() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_INCORRECT_DYNAMIC_CALL));
	}

	/**
	 * Test accessing an undeclared member variable with the same name as a method on an object with a __get() method.
	 *
	 * @return void
	 * @rapid-unit Checks:PropertyFetchCheck:Can access unknown property with same name as method on object with a __get() method
	 */
	public function testAccessingUndeclaredMemberVariableSameNameAsMethodYes__get() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.2.inc', ErrorConstants::TYPE_INCORRECT_DYNAMIC_CALL));
	}

	/**
	 * Test accessing undeclared member variables with the same name as methods on objects with and without a __get() method in different
	 * stages of an object hierarchy. The parent doesn't have a __get() method so it should fail. The child does have a __get() so it
	 * should succeed. The grandchild doesn't have a __get() but its parent does so it should also be safe.
	 *
	 * @return void
	 * @rapid-unit Checks:PropertyFetchCheck:Can access unknown property with same name as method on object with a __get() method in self or parent
	 */
	public function testAccessingUndeclaredMemberVariableSameNameAsMethodHierarchy() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.3.inc', ErrorConstants::TYPE_INCORRECT_DYNAMIC_CALL));
	}
}