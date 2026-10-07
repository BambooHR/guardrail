<?php

declare(strict_types=1);

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
		// 6 valid generators (2x withYield, 2x withYieldFrom, interface, and abstract) = 0 errors
		$this->assertEquals(0, $this->runAnalyzerOnFile('-generators.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to pass generator return types");
		// 6 invalid generators (3 class methods + 3 standalone functions) = 6 errors
		$this->assertEquals(6, $this->runAnalyzerOnFile('-generators-fail.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to fail generator return types");
	}
	public function testEmptyFunctionsPass() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('-empty-functions.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to pass empty functions with various return type");
	}
	public function testEmptyFunctionsFail() {
		// 18 invalid empty functions (int, string, array, bool, callable, object, self, static, ?int, int | string, iterable, Throwable, null, null|string) = 18 errors
		// 18 invalid empty functions in a class (int, string, array, bool, callable, object, self, static, ?int, int | string, iterable, Throwable, null, null|string) = 18 errors
		$this->assertEquals(36, $this->runAnalyzerOnFile('-empty-functions-fail.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to fail empty functions with various return type");
	}

	public function testNoReturn() {
		// 8 invalid (4 class methods + 4 standalone functions)
		$this->assertEquals(8, $this->runAnalyzerOnFile('-no-return-fail.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to fail no return");
	}

	public function testObjectReturnFail() {
		// 2 invalid (1 class method + 1 standalone function)
		$this->assertEquals(2, $this->runAnalyzerOnFile('-object-fail.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to fail object return");
	}

	public function testVoidAndNeverReturnTypes() {
		// 2 valid (voidWithEmptyReturn, voidWithNoReturn) = 0 errors
		$this->assertEquals(0, $this->runAnalyzerOnFile('-void-never.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to pass void and never return types");
	}

	public function testVoidAndNeverReturnTypesFail() {
		// 3 invalid (voidWithValue, neverWithValue, intWithEmptyReturn) = 3 errors
		$this->assertEquals(3, $this->runAnalyzerOnFile('-void-never-fail.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to fail void and never return types");
	}

	public function testSpecialCases() {
		// All valid: self return type, standalone function, closures, arrow functions = 0 errors
		$this->assertEquals(0, $this->runAnalyzerOnFile('-special-cases.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to pass special cases (self, closures, standalone functions)");
	}

	public function testStandardReturnTypes() {
		// All valid: int, string, array, bool, float, object, callable, nullable, union types = 0 errors
		$this->assertEquals(0, $this->runAnalyzerOnFile('-standard-returns.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to pass standard return types");		// Invalid type mismatches: 20 errors (10 in class methods + 10 in standalone functions)
	}

	public function testStandardReturnTypesFail() {
		// 20 invalid type mismatches (10 in class methods + 10 in standalone functions)
		$this->assertEquals(20, $this->runAnalyzerOnFile('-standard-returns-fail.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to fail standard return types");
	}
}
