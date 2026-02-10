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
	public function testEmptyFunctionsFail() {
		// 8 invalid empty functions (int, string, array, bool, callable, object, self, static) = 8 errors
		$this->assertEquals(8, $this->runAnalyzerOnFile('-empty-functions-fail.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to fail empty functions with various return types" );
	}
}
