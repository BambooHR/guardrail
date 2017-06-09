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
		$testFile = dirname(__FILE__) . '/TestData/' . basename(__FILE__, '.php') . '.1.inc';
		$this->assertEquals(1, $this->runAnalyzerOnFile($testFile, ErrorConstants::TYPE_MISSING_CONSTRUCT));
	}


}