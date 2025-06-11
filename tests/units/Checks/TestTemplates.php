<?php

namespace BambooHR\Guardrail\Tests\units\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

class TestTemplates extends TestSuiteSetup {


	function testValidTemplate() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.1.inc', "Standard.*", [
			"ignore-errors" => [
				"Standard.Autoload.Unsafe", ErrorConstants::TYPE_OPEN_API_ATTRIBUTE_DOCUMENTATION_CHECK,
				ErrorConstants::TYPE_SERVICE_METHOD_DOCUMENTATION_CHECK,
				"Standard.Global.Function"]
		]));
	}


	function testFunctionTemplate() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.2.inc', "Standard.*",["ignore-errors"=>["Standard.Autoload.Unsafe", "Standard.Global.Function"]]));
	}

	function testTemplateParam() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.3.inc', "Standard.*",["ignore-errors"=>["Standard.Autoload.Unsafe", "Standard.Global.Function"]]));
	}
}