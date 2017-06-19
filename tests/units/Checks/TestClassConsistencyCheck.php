<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\CatchCheck;
use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;
use const IDNA_ERROR_CONTEXTJ;

/**
 * Class TestCatchCheck
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class TestClassConsistencyCheck extends TestSuiteSetup {

	/**
	 * testDuplicateMethods
	 *
	 * @return void
	 * @rapid-unit Checks:ClassConsistencyCheck:Emits error when duplicate methods are found in the same class.
	 */
	public function testDuplicateMethods() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_DUPLICATE_METHOD));
	}

	/**
	 * testDuplicateProperties
	 *
	 * @return void
	 * @rapid-unit Checks:ClassConsistencyCheck:Emits error when duplicate properties are found in the same class.
	 */
	public function testDuplicateProperties() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.2.inc', ErrorConstants::TYPE_DUPLICATE_PROPERTY));
	}

	/**
	 * testInheritedDuplicateTraitProperty
	 *
	 * @return void
	 * @rapid-unit Checks:ClassConsistencyCheck:Emits an error when importing a trait would lead to a duplicate property.
	 */
	public function testInheritedDuplicateTraitProperty() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.3.inc', ErrorConstants::TYPE_DUPLICATE_PROPERTY));
	}
}