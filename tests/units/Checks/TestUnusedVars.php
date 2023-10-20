<?php

namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Class TestInterfaceCheck
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class TestUnusedVars extends TestSuiteSetup {
	public function testUnusedVariable() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_UNUSED_VARIABLE), "");
	}

	public function testUndefinedVariable2() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.2.inc', ErrorConstants::TYPE_UNUSED_VARIABLE), "");
		$this->assertEquals(0, $this->runAnalyzerOnFile('.2.inc', ErrorConstants::TYPE_UNKNOWN_VARIABLE), "");
	}

	public function testUndefinedVariable3() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.3.inc', ErrorConstants::TYPE_UNUSED_VARIABLE), "");
	}

	public function testReferenceVariablesInScopeAreNotMarkedAsUnused() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.4.inc', ErrorConstants::TYPE_UNUSED_VARIABLE), "");
		$this->assertEquals(0, $this->runAnalyzerOnFile('.4.inc', ErrorConstants::TYPE_AUTOLOAD_ERROR), "");
	}

}