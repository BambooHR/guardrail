<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Class TestClassConstantCheck
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class TestComplexityCheck extends TestSuiteSetup {

	/**
	 * testManyOrClauses
	 *
	 * @return void
	 * @rapid-unit Checks:CyclomaticComplexityCheck:OrClauses Many Or clauses in a single statement
	 */
	public function testManyOrClauses() {
		// As of 3/25/2024, this check emits a metric, rather than emitting an error. So we expect 0 errors in this test.
		// Will follow up quickly with the ability to emit based on exceeding a customizable metric threshold.
		$this->assertEquals(0, $this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_METRICS_COMPLEXITY));
	}
}
