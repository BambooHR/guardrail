<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Class TestCallCheck
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class TestCallCheck extends TestSuiteSetup {

	/**
	 * @return void
	 * @rapid-unit Checks:CallCheck:Emits error when positional arg follows named arg
	 */
	public function testPositionalAfterNamedArg() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}

	/**
	 * @return void
	 * @rapid-unit Checks:CallCheck:Emits error when named parameter is passed twice
	 */
	public function testDuplicateNamedParam() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.2.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}

	/**
	 * @return void
	 * @rapid-unit Checks:CallCheck:Emits error when unknown named parameter is used
	 */
	public function testUnknownNamedParam() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.3.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}

	/**
	 * @return void
	 * @rapid-unit Checks:CallCheck:Emits error when required parameter is not passed
	 */
	public function testRequiredParamNotPassed() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.4.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}

	/**
	 * @return void
	 * @rapid-unit Checks:CallCheck:Emits error when splat operator used on non-traversable
	 */
	public function testSplatOnNonTraversable() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.5.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}

	/**
	 * @return void
	 * @rapid-unit Checks:CallCheck:Emits error when non-reference passed to reference parameter
	 */
	public function testNonReferenceToReferenceParam() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.6.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}

	/**
	 * @return void
	 * @rapid-unit Checks:CallCheck:Emits error when incompatible type is passed (array to int)
	 */
	public function testTypeMismatch() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.7.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}

	/**
	 * @return void
	 * @rapid-unit Checks:CallCheck:Does not emit error for valid function call
	 */
	public function testValidCall() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.8.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}

	/**
	 * @return void
	 * @rapid-unit Checks:CallCheck:Does not emit error for valid function call with variadic placeholder
	 */
	public function testValidCallWithVariadicPlaceholder() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.9.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}

	/**
	 * @return void
	 * @rapid-unit Checks:CallCheck:Does not emit error when splat operator used on traversable array
	 */
	public function testSplatTraversable() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.10.inc', ErrorConstants::TYPE_SIGNATURE_TYPE));
	}

	/**
	 * @return void
	 * @rapid-unit Checks:CallCheck:Does not emit error when passing value to template type parameter
	 */
	public function testTemplateTypeParameter() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.11.inc', ErrorConstants::TYPE_SIGNATURE_TYPE, ['DocBlockGenerics' => true]));
	}

}