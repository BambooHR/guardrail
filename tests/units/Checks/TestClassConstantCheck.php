<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Class TestClassConstantCheck
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class TestClassConstantCheck extends TestSuiteSetup {

	/**
	 * testCantUseParentWithNoParent
	 *
	 * @return void
	 * @rapid-unit Checks:ClassConstantCheck:Cannot access parent constant inside a class that has no parent
	 */
	public function testCantUseParentWithNoParent() {
		$testFile = dirname(__FILE__) . '/TestData/' . basename(__FILE__, '.php') . '.1.inc';
		$this->assertEquals(1, $this->runAnalyzerOnFile($testFile, ErrorConstants::TYPE_SCOPE_ERROR));
	}

	/**
	 * testAccessParentOutsideOfClass
	 *
	 * @return void
	 * @rapid-unit Checks:ClassConstantCheck:Cannot access parent constant outside of class
	 */
	public function testAccessParentOutsideOfClass() {
		$testFile = dirname(__FILE__) . '/TestData/' . basename(__FILE__, '.php') . '.2.inc';
		$this->assertEquals(1, $this->runAnalyzerOnFile($testFile, ErrorConstants::TYPE_SCOPE_ERROR));
	}

	/**
	 * testAccessSelfOutsideOfClass
	 *
	 * @return void
	 * @rapid-unit Checks:ClassConstantCheck:Cannot access constant using self:: outside of class
	 */
	public function testAccessSelfOutsideOfClass() {
		$testFile = dirname(__FILE__) . '/TestData/' . basename(__FILE__, '.php') . '.3.inc';
		$this->assertEquals(1, $this->runAnalyzerOnFile($testFile, ErrorConstants::TYPE_SCOPE_ERROR));
	}

	/**
	 * testAccessStaticOutsideOfClass
	 *
	 * @return void
	 * @rapid-unit Checks:ClassConstantCheck:Cannot access constant using static:: outside of class
	 */
	public function testAccessStaticOutsideOfClass() {
		$testFile = dirname(__FILE__) . '/TestData/' . basename(__FILE__, '.php') . '.4.inc';
		$this->assertEquals(1, $this->runAnalyzerOnFile($testFile, ErrorConstants::TYPE_SCOPE_ERROR));
	}

	/**
	 * testAccessingUnknownClassConstant
	 *
	 * @return void
	 * @rapid-unit Checks:ClassConstantCheck:Catches trying to access unknown constant inside class
	 */
	public function testAccessingUnknownClassConstant() {
		$testFile = dirname(__FILE__) . '/TestData/' . basename(__FILE__, '.php') . '.5.inc';
		$this->assertEquals(1, $this->runAnalyzerOnFile($testFile, ErrorConstants::TYPE_UNKNOWN_CLASS_CONSTANT));
	}

	/**
	 * testAccessingUnknownClass
	 *
	 * @return void
	 * @rapid-unit Checks:ClassConstantCheck:Cacthes trying to access constant in unknown class
	 */
	public function testAccessingUnknownClass() {
		$testFile = dirname(__FILE__) . '/TestData/' . basename(__FILE__, '.php') . '.6.inc';
		$this->assertEquals(1, $this->runAnalyzerOnFile($testFile, ErrorConstants::TYPE_UNKNOWN_CLASS));
	}
}
