<?php namespace BambooHR\Guardrail\Tests\units\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Class TestClassReferencedAsString
 * @package BambooHR\Guardrail\Tests\units\Checks
 */
class TestClassStoredAsVariableCheck extends TestSuiteSetup {


	/**
	 * testClassVariableClass
	 *
	 * @return void
	 * @rapid-unit Checks:ClassReferencedAsString:Class referenced as string validation
	 */
	public function testClassVariableClass() {
		//$this->assertEquals(2, $this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_CLASS_STORED_VARIABLE));
	}

	/**
	 * testClassVariableAbstractClass
	 *
	 * @return void
	 * @rapid-unit Checks:ClassReferencedAsString:Class referenced as string validation
	 */
	public function testClassVariableAbstractClass() {
		//$this->assertEquals(1, $this->runAnalyzerOnFile('.2.inc', ErrorConstants::TYPE_CLASS_STORED_VARIABLE));
	}

	/**
	 * testClassVariableNamespacedClass
	 *
	 * @return void
	 * @rapid-unit Checks:ClassReferencedAsString:Class referenced as string validation
	 */
	public function testClassVariableNamespacedClass() {
		//$this->assertEquals(1, $this->runAnalyzerOnFile('.3.inc', ErrorConstants::TYPE_CLASS_STORED_VARIABLE));
	}

	/**
	 * testClassVariableNamespacedAbstractClass
	 *
	 * @return void
	 * @rapid-unit Checks:ClassReferencedAsString:Class referenced as string validation
	 */
	public function testClassVariableNamespacedAbstractClass() {
		//$this->assertEquals(1, $this->runAnalyzerOnFile('.4.inc', ErrorConstants::TYPE_CLASS_STORED_VARIABLE));
	}

	/**
	 * testClassVariableClassNotInString
	 *
	 * @return void
	 * @rapid-unit Checks:ClassReferencedAsString:Class referenced as string validation
	 */
	public function testClassVariableClassNotInString() {
		//$this->assertEquals(0, $this->runAnalyzerOnFile('.5.inc', ErrorConstants::TYPE_CLASS_STORED_VARIABLE));
	}

	/**
	 * testClassVariableNamespacedClassNotInString
	 *
	 * @return void
	 * @rapid-unit Checks:ClassReferencedAsString:Class referenced as string validation
	 */
	public function testClassVariableNamespacedClassNotInString() {
		//$this->assertEquals(0, $this->runAnalyzerOnFile('.6.inc', ErrorConstants::TYPE_CLASS_STORED_VARIABLE));
	}

	/**
	 * testNamspacedClassVariableClass
	 *
	 * @return void
	 * @rapid-unit Checks:ClassReferencedAsString:Class referenced as string validation
	 */
	public function testNamespacedClassVariableClass() {
		//$this->assertEquals(1, $this->runAnalyzerOnFile('.7.inc', ErrorConstants::TYPE_CLASS_STORED_VARIABLE));
	}

}