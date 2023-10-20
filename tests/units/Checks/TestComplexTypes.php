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
		$this->assertEquals(0, $output->getErrorCount(), "Failed");
	}

	public function testUnionTypesCall() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.3.inc', ErrorConstants::TYPE_SIGNATURE_TYPE), "Failed to validate union types" );
	}

	//TODO THIS TEST IS NOT MATCHING THE FAILURE THAT WE ARE SEEING IN PG
	public function testArrayAppendGetsRightType() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.4.inc', ErrorConstants::TYPE_SIGNATURE_TYPE), "Failed to detect proper type of array" );
	}

	public function testTypeInferenceChangesWithNullCheck() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.5.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed change Type Inference to non null" );
		$this->assertEquals(0, $this->runAnalyzerOnFile('.5.inc', ErrorConstants::TYPE_NULL_DEREFERENCE), "Failed change Type Inference to non null" );
	}
}
