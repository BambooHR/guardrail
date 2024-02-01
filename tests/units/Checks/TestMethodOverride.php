<?php

namespace BambooHR\Guardrail\Tests\units\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

class TestMethodOverride extends TestSuiteSetup {
	function testSuccessfulOverride() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_UNKNOWN_METHOD), "Detected an error when the #[Override] was valid");
		$this->assertEquals(0, $this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_OVERRIDE_BASE_CLASS), "Detected an error when the #[Override] was valid");
	}
	function testDoesntExtend() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.2.inc', ErrorConstants::TYPE_OVERRIDE_BASE_CLASS), "Failed to detect #[Override] of in a base class");
	}
	function testParentDoesntHaveMethod() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.3.inc', ErrorConstants::TYPE_UNKNOWN_METHOD), "Failed to detect #[Override] when no parent implements the same method");
	}
}