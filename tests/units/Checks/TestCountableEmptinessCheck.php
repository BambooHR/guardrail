<?php

namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class TestCountableEmptinessCheck extends TestSuiteSetup {
	/**
	 * testUseIsEmptyInsteadOfEmtpyWarning
	 *
	 * @return void
	 * @rapid-unit Checks:CountableEmptinessCheck:Emits error when using empty() on a countable
	 */
	public function testUseIsEmptyInsteadOfEmtpyWarning() {
		$output = $this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_COUNTABLE_EMPTINESS_CHECK);
		$this->assertEquals(1, $output, "Expects emptiness check to fail");
	}

	/**
	 * runAnalyzerOnFile
	 *
	 * @return void
	 * @rapid-unit Checks:CountableEmptinessCheck:Does not emit errors when using ->isEmpty() on a countable
	 */
	public function testCorrectEmptinessCheck() {
		$output = $this->runAnalyzerOnFile('.2.inc', ErrorConstants::TYPE_COUNTABLE_EMPTINESS_CHECK);
		$this->assertEquals(0, $output, "Expects emptiness check to pass");
	}
}
