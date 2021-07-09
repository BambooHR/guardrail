<?php

namespace BambooHR\Guardrail\Tests\units\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

class TestPropertyStoreCheck extends TestSuiteSetup {

	function testGoodAssigns() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_ASSIGN_MISMATCH));
	}

	function testBadAssigns() {
		$this->assertEquals(2, $this->runAnalyzerOnFile('.2.inc', ErrorConstants::TYPE_ASSIGN_MISMATCH));
		$this->assertEquals(1, $this->runAnalyzerOnFile('.2.inc', ErrorConstants::TYPE_ASSIGN_MISMATCH_SCALAR));
	}
}