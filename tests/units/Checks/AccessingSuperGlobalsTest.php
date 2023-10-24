<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\AccessingSuperGlobalsCheck;
use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Class AccessingSuperGlobalsTest
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class AccessingSuperGlobalsTest extends TestSuiteSetup {

	/**
	 * testRunAccessingSuperGlobalGlobalExpressions
	 *
	 * @return void
	 * @rapid-unit Checks:AccessingSuperGlobals:Calling the $GLOBALS array emits an error
	 */
	public function testRunAccessingSuperGlobalGlobalExpressions() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_GLOBAL_EXPRESSION_ACCESSED));
	}

	/**
	 * testRunAccessingSuperGlobalGlobalVariables
	 *
	 * @return void
	 * @rapid-unit Checks:AccessingSuperGlobals:Calling `global $var` emits an error
	 */
	public function testRunAccessingSuperGlobalGlobalVariables() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.2.inc', ErrorConstants::TYPE_GLOBAL_STRING_ACCESSED));
	}

	/**
	 * testRunAccessingSuperGlobalVariableOnly
	 *
	 * @return void
	 * @rapid-unit Checks:AccessingSuperGlobals:Assigning $GLOBALS emits an error
	 */
	public function testRunAccessingSuperGlobalVariableOnly() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.3.inc', ErrorConstants::TYPE_GLOBAL_EXPRESSION_ACCESSED));
	}
}