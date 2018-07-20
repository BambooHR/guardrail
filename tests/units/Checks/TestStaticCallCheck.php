<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;


/**
 * Class TestStaticCallCheck
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class TestStaticCallCheck extends TestSuiteSetup {

	/**
	 * testParentCallInClosureEmitsNoErrors
	 *
	 * @return void
	 * @rapid-unit Checks:FunctionCallCheck:Catches all dangerous method calls and emits errors
	 */
	public function testParentCallInClosureEmitsNoErrors() {
		$this->assertEquals(1,$this->runAnalyzerOnFile('.1.inc',[ErrorConstants::TYPE_INCORRECT_DYNAMIC_CALL, ErrorConstants::TYPE_SCOPE_ERROR]));
	}

}
