<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Class AccessingSuperGlobalsTest
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class DeprecatedFunctionMetricTest extends TestSuiteSetup {

	/**
	 * testRunDeprecatedMetricOnFunction
	 *
	 * @return void
	 */
	public function testRunDeprecatedMetricOnFunction() {
		$output = $this->getOutputFromAnalyzer('.1.inc', ErrorConstants::TYPE_METRICS_DEPRECATED_FUNCTIONS);
		$this->assertEquals(1, $this->getMetricCountByName($output, ErrorConstants::TYPE_METRICS_DEPRECATED_FUNCTIONS));
	}

	/**
	 * testRunDeprecatedMetricOnClassMethod
	 *
	 * @return void
	 */
	public function testRunDeprecatedMetricOnClassMethod() {
		$output = $this->getOutputFromAnalyzer('.2.inc', ErrorConstants::TYPE_METRICS_DEPRECATED_FUNCTIONS);
		$this->assertEquals(1, $this->getMetricCountByName($output, ErrorConstants::TYPE_METRICS_DEPRECATED_FUNCTIONS));
	}
}