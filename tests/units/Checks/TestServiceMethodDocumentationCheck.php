<?php

namespace BambooHR\Guardrail\Tests\units\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

class TestServiceMethodDocumentationCheck extends TestSuiteSetup {
	/**
	 * testParametersSetAndUnset
	 *
	 * @return void
	 */
	public function testParametersSetAndUnset() {
		$this->assertEquals(5, $this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_SERVICE_METHOD_DOCUMENTATION_CHECK), "");
	}

	/**
	 * testParametersSetAndUnset
	 *
	 * @return void
	 */
	public function testReturnTypesSetAndUnset() {
		$this->assertEquals(4, $this->runAnalyzerOnFile('.2.inc', ErrorConstants::TYPE_SERVICE_METHOD_DOCUMENTATION_CHECK), "");
	}

	/**
	 * @return void
	 */
	public function testSimpleTypesMatchingAndUnMatchingDocBlocks() {
		$this->assertEquals(7, $this->runAnalyzerOnFile('.3.inc', ErrorConstants::TYPE_SERVICE_METHOD_DOCUMENTATION_CHECK), "");
	}

	/**
	 * @return void
	 */
	public function testComplexTypesMatchingAndUnMatchingDocBlocks() {
		$this->assertEquals(13, $this->runAnalyzerOnFile('.4.inc', ErrorConstants::TYPE_SERVICE_METHOD_DOCUMENTATION_CHECK), "");
	}
}