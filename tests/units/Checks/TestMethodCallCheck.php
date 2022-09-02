<?php

namespace BambooHR\Guardrail\Tests\units\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Class TestMethodCallCheck
 *
 * @package BambooHR\Guardrail\Tests\units\Checks
 */
class TestMethodCallCheck extends TestSuiteSetup {
	/**
	 * Test an unknown method check on a class with a method_exists() check prior to calling the method. See TestMethodCallCheck.1.inc for the example class.
	 * If method_exists() is called before invoking a method on an object, an ErrorConstants::TYPE_UNKNOWN_METHOD shouldn't be omitted.
	 *
	 * @return void
	 */
	public function testUnknownMethodWithExistsCheck(): void {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_UNKNOWN_METHOD));
	}
}