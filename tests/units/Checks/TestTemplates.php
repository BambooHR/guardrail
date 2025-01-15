<?php

namespace BambooHR\Guardrail\Tests\units\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

class TestTemplates extends TestSuiteSetup {


	function testValidTemplate() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.1.inc',"Standard.*", ["ignore-errors"=>["Standard.Autoload.Unsafe", ErrorConstants::TYPE_WEB_API_DOCUMENTATION_CHECK]]));

	}


	function testFunctionTemplate() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.2.inc', "Standard.*",["ignore-errors"=>["Standard.Autoload.Unsafe"]]));
	}

	function testTemplateParam() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.3.inc', "Standard.*",["ignore-errors"=>["Standard.Autoload.Unsafe"]]));
	}
}