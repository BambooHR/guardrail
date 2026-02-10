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
	public function testStrictFail() {
		// 15 class and 15 global functions that are in `strict type` mode
		$this->assertEquals(30, $this->runAnalyzerOnFile('-strict.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to pass strict mode");
	}

	public function testNonStrictPass() {
		// 15 class and 15 global functions that are in `non-strict type` mode
		$this->assertEquals(0, $this->runAnalyzerOnFile('-non-strict.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to pass non-strict mode");
	}
}
