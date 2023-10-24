<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Class TestConstructorCheck
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class TestConstructorCheck extends TestSuiteSetup {

	/**
	 * testCheckMissingCallToParentConstructor
	 *
	 * @return void
	 * @rapid-unit Checks:ConstructorCheck:Catches overriding constructor that does not call the parent constructor
	 */
	public function testCheckMissingCallToParentConstructor() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_MISSING_CONSTRUCT));
	}
}