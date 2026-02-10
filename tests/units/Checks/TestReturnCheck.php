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
		$this->assertEquals(10, $this->runAnalyzerOnFile('-strict.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed to pass strict mode");
	}

}
