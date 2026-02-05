<?php

namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Test that ConstFetch evaluator correctly infers types for runtime PHP constants
 * like PHP_VERSION, FILTER_VALIDATE_EMAIL, true, false, null, etc.
 */
class TestConstFetch extends TestSuiteSetup
{

	/**
	 * Test that runtime PHP constants are correctly typed
	 *
	 * @return void
	 */
	public function testRuntimeConstantTypes() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.1.inc', ''));
	}

	/**
	 * Test that true, false, and null are correctly typed
	 *
	 * @return void
	 */
	public function testBooleanAndNullConstants() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.2.inc', ''));
	}
}
