<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;


/**
 * Class TestFunctionCalCheck
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class TestGenerator extends TestSuiteSetup {

	public function testGeneratorShouldPass() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to validate generator return type" );
	}

	public function testGeneratorShouldFailNoYield() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.2.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to validate generator return type" );
	}

	public function testGeneratorShouldFailEmptyFunction() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.3.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to validate generator return type" );
	}
}