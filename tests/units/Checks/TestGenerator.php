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

	public function testAbstractMethodShouldPass() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.6.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to validate abstract method should pass" );
	}

	public function testMixedReturnTypeEmptyFunctionShouldPass() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.7.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to validate mixed return type should pass with empty function" );
	}

	public function testVoidReturnTypeWithEmptyReturnShouldPass() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.8.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to validate void return type with empty return should pass" );
	}

	public function testVoidReturnTypeWithValueShouldFail() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.9.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to validate void return type with value should fail" );
	}

	public function testNeverReturnTypeWithValueShouldFail() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.10.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to validate never return type with value should fail" );
	}

	public function testReturnWithoutValueInTypedFunctionShouldFail() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.11.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to validate return without value in typed function should fail" );
	}

	public function testSelfReturnTypeShouldPass() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.12.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to validate self return type should pass" );
	}

	public function testGeneratorWithYieldFromShouldPass() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.13.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to validate generator with yield from should pass" );
	}

	public function testStandaloneFunctionAndClosuresShouldPass() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.14.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to validate standalone function and closures should pass" );
	}

	public function testInterfaceMethodWithGeneratorShouldPass() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.15.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to validate interface method with Generator should pass" );
	}

	public function testTrulyEmptyGeneratorFunctionShouldFail() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.16.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to validate truly empty generator function should fail" );
	}
}