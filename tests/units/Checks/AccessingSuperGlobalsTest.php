<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\AccessingSuperGlobalsCheck;
use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Class AccessingSuperGlobalsCheckTest
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class AccessingSuperGlobalsCheckTest extends TestSuiteSetup {

	/**
	 * testRunAccessingSuperGlobalGlobalExpressions
	 *
	 * @return void
	 * @rapid-unit Checks:AccessingSuperGlobals:Calling the $GLOBALS array emits an error
	 */
	public function testRunAccessingSuperGlobalGlobalExpressions() {
		$testFile = dirname(__FILE__) . '/TestData/' . basename(__FILE__, '.php') . '.1.inc';
		$this->assertEquals(1, $this->runAnalyzerOnFile($testFile, ErrorConstants::TYPE_GLOBAL_EXPRESSION_ACCESSED));
	}

	/**
	 * testRunAccessingSuperGlobalGlobalVariables
	 *
	 * @return void
	 * @rapid-unit Checks:AccessingSuperGlobals:Calling `global $var` emits an error
	 */
	public function testRunAccessingSuperGlobalGlobalVariables() {
		$testFile = dirname(__FILE__) . '/TestData/' . basename(__FILE__, '.php') . '.2.inc';
		$this->assertEquals(1, $this->runAnalyzerOnFile($testFile, ErrorConstants::TYPE_GLOBAL_STRING_ACCESSED));
	}

	/**
	 * testRunAccessingSuperGlobalVariableOnly
	 *
	 * @return void
	 * @rapid-unit Checks:AccessingSuperGlobals:Assigning $GLOBALS emits an error
	 */
	public function testRunAccessingSuperGlobalVariableOnly() {
		$testFile = dirname(__FILE__) . '/TestData/' . basename(__FILE__, '.php') . '.3.inc';
		$this->assertEquals(1, $this->runAnalyzerOnFile($testFile, ErrorConstants::TYPE_GLOBAL_EXPRESSION_ACCESSED));
	}
}