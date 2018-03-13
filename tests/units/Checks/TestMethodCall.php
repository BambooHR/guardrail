<?php

namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Class TestMethodCall
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class TestMethodCall extends TestSuiteSetup {
	/**
	 * Test the first() method on an Illuminate Collection. It should not fail after calling sortByDesc.
	 *
	 * @return void
	 * @rapid-unit Checks:UnknownClassMethodCheck:Illuminate collection first() should not fail
	 */
	public function testFirstFromCollectionDoesntFail() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_UNKNOWN_METHOD));
	}
}