<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\CatchCheck;
use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Class TestCatchCheck
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class TestNullablePropertiesCheck extends TestSuiteSetup {

	public function testNullablePropertyInNullableObject() {

		$result = $this->runAnalyzerOnFile('.1.inc', [ ErrorConstants::TYPE_NULL_DEREFERENCE, ErrorConstants::TYPE_NULL_METHOD_CALL]);
		$this->assertEquals(0, $result);
	}

	public function testNullablePropertyChainInNullableObject() {
		$result = $this->runAnalyzerOnFile('.2.inc', [ ErrorConstants::TYPE_NULL_DEREFERENCE, ErrorConstants::TYPE_NULL_METHOD_CALL]);
		$this->assertEquals(0, $result);
	}

	public function testIsNullCheck() {
		$result = $this->runAnalyzerOnFile('.3.inc', [ ErrorConstants::TYPE_NULL_DEREFERENCE, ErrorConstants::TYPE_NULL_METHOD_CALL]);
		$this->assertEquals(0, $result);
	}
}

