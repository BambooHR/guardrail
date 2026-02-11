<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Class TestImagickCheck
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class TestImagickCheck extends TestSuiteSetup {
	/**
	 * @return void
	 * @rapid-unit Checks:ImagickCheck:Emits error when Imagick is instantiated
	 */
	public function testImagickInstantiationEmitsError(): void {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_UNSAFE_IMAGICK));
	}

	/**
	 * @return void
	 * @rapid-unit Checks:ImagickCheck:Does not emit when other classes are instantiated
	 */
	public function testNonImagickInstantiationIsSafe(): void {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.2.inc', ErrorConstants::TYPE_UNSAFE_IMAGICK));
	}
}
