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
		$this->assertEquals(1, $this->runAnalyzerOnFile('.2.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to validate generator return type without yield" );
	}

	public function testGeneratorShouldFailEmptyFunction() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.3.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to validate generator return type in empty function" );
	}

	public function testNotAGeneratorEmptyFunctionNoneReturnShouldPass() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.4.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to validate none return type should pass with empty function" );
	}

	public function testNotAGeneratorEmptyFunctionDifferentReturnShouldFail() {
		$this->assertEquals(8, $this->runAnalyzerOnFile('.5.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to validate int return type should fail with empty function" );
	}
}