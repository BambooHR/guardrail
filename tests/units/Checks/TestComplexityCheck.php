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
		$this->assertEquals(1, $this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_METRICS_COMPLEXITY));
	}
}
