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
}