<?php

namespace BambooHR\Guardrail\Tests\units\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

class TestOpenApiAttributeDocumentationCheck extends TestSuiteSetup {
	/**
	 * testApiAttributeIsPresent
	 *
	 * @return void
	 */
	public function testApiAttributeIsPresent() {
		$this->assertEquals(2, $this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_OPEN_API_ATTRIBUTE_DOCUMENTATION_CHECK), "");
	}

	/**
	 * @return void
	 */
	public function testOnlyErrorsOnPublicMethods() {
		$this->assertEquals(2, $this->runAnalyzerOnFile('.2.inc', ErrorConstants::TYPE_OPEN_API_ATTRIBUTE_DOCUMENTATION_CHECK), "");
	}

	/**
	 * @return void
	 */
	public function testMethodWithDeprecatedAttribute() {
		$output = $this->getOutputFromAnalyzer('.3.inc', ErrorConstants::TYPE_METRICS_DEPRECATED_FUNCTIONS);
		$this->assertEquals(1, $this->getMetricCountByName($output, ErrorConstants::TYPE_METRICS_DEPRECATED_FUNCTIONS));
	}

	/**
	 * @return void
	 */
	public function testWithAndWithoutVectorSearchPhrases() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.4.inc', ErrorConstants::TYPE_OPEN_API_ATTRIBUTE_DOCUMENTATION_SEARCH_PHRASES_CHECK), "");
	}
}