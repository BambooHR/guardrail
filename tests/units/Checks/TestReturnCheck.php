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

	public function testGenerators() {
		// 2 valid generators (withYield, withYieldFrom) + 0 errors from interface/abstract = 0 errors
		$this->assertEquals(0, $this->runAnalyzerOnFile('-generators.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to validate generator return types" );
		// 3 invalid generators (noYieldWithReturn, emptyWithComment, trulyEmpty) = 3 errors
		$this->assertEquals(3, $this->runAnalyzerOnFile('-generators-fail.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to validate generator return types" );
	}

	public function testEmptyFunctions() {
		// 3 valid empty functions (none, void, mixed) + 1 abstract = 0 errors
		$this->assertEquals(0, $this->runAnalyzerOnFile('-empty-functions.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to validate empty functions with various return types" );
		// 8 invalid empty functions (int, string, array, bool, callable, object, self, static) = 8 errors
		$this->assertEquals(8, $this->runAnalyzerOnFile('-empty-functions-fail.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to validate empty functions with various return types" );
	}

	public function testVoidAndNeverReturnTypes() {
		// 2 valid (voidWithEmptyReturn, voidWithNoReturn) = 0 errors
		$this->assertEquals(0, $this->runAnalyzerOnFile('-void-never.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to validate void and never return types" );
		// 3 invalid (voidWithValue, neverWithValue, intWithEmptyReturn) = 3 errors
		$this->assertEquals(3, $this->runAnalyzerOnFile('-void-never-fail.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to validate void and never return types" );
	}

	public function testSpecialCases() {
		// All valid: self return type, standalone function, closures, arrow functions = 0 errors
		$this->assertEquals(0, $this->runAnalyzerOnFile('-special-cases.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to validate special cases (self, closures, standalone functions)" );
	}

	public function testStandardReturnTypes() {
		// All valid: int, string, array, bool, float, object, callable, nullable, union types = 0 errors
		$this->assertEquals(0, $this->runAnalyzerOnFile('-standard-returns.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to validate standard return types" );
		// Invalid type mismatches: 18 errors (9 in class methods + 9 in standalone functions) in strict mode
		$this->assertEquals(18, $this->runAnalyzerOnFile('-standard-returns-fail.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to validate invalid return type mismatches" );
	}
}