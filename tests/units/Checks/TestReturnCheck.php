<?php declare(strict_types=1);

namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;


/**
 * Class TestReturnCheck
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class TestReturnCheck extends TestSuiteSetup {
	public function testEmptyFunctions() {
		// 10 valid empty functions (none, 2x with space, void, never, null, nullable, union, mixed, and 1 abstract) = 0 errors
		$this->assertEquals(0, $this->runAnalyzerOnFile('-empty-functions.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to pass empty functions with various return types" );
	}

	public function testVoidAndNeverReturnTypes() {
		// 2 valid (voidWithEmptyReturn, voidWithNoReturn) = 0 errors
		$this->assertEquals(0, $this->runAnalyzerOnFile('-void-never.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to pass void and never return types" );
	}

	public function testVoidAndNeverReturnTypesFail() {
		// 3 invalid (voidWithValue, neverWithValue, intWithEmptyReturn) = 3 errors
		$this->assertEquals(3, $this->runAnalyzerOnFile('-void-never-fail.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to fail void and never return types" );}

	public function testSpecialCases() {
		// All valid: self return type, standalone function, closures, arrow functions = 0 errors
		$this->assertEquals(0, $this->runAnalyzerOnFile('-special-cases.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to pass special cases (self, closures, standalone functions)" );
	}

	public function testStandardReturnTypes() {
		// All valid: int, string, array, bool, float, object, callable, nullable, union types = 0 errors
		$this->assertEquals(0, $this->runAnalyzerOnFile('-standard-returns.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to pass standard return types" );		// Invalid type mismatches: 20 errors (10 in class methods + 10 in standalone functions)
	}

	public function testStandardReturnTypesFail() {
		// 34 invalid type mismatches (17 in class methods + 17 in standalone functions)
		$this->assertEquals(34, $this->runAnalyzerOnFile('-standard-returns-fail.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to fail standard return types" );
	}
}