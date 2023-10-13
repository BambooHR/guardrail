<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;


/**
 * Class TestFunctionCalCheck
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class TestComplexTypes extends TestSuiteSetup {

	public function testIncompleteCompound() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_SIGNATURE_TYPE), "Failed to detect traits with properties" );
	}

	public function testCompleteCompound() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.2.inc', ErrorConstants::TYPE_SIGNATURE_TYPE), "Failed to detect traits with properties" );
	}

	public function testWithUntypedDatetime() {
		//TODO: This test is failing type check thinking that $one is a datetime type even in the else
		$func = <<<'ENDCODE'
				function method($one, $two = null) {
					if ($one instanceof \DateTime || $one instanceof \Date) {
						$date = $one;
					} else {
						$test = explode('-', $one);
					}
				}
		ENDCODE;

		$output = $this->analyzeStringToOutput("test.php", $func, ErrorConstants::TYPE_SIGNATURE_TYPE, ["basePath" => "/"]);
		var_dump($output->renderResults());
		$this->assertEquals(0, $output->getErrorCount(), "Failed");
	}
}
