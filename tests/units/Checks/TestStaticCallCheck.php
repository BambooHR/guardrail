<?php

namespace BambooHR\Guardrail\Tests\units\Checks;


use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;


class TestStaticCallCheck extends TestSuiteSetup {

	/**
	 *
	 * @return void
	 */
	public function testParentInClassClosure() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.ClosureParentClass.inc',ErrorConstants::TYPE_INCORRECT_DYNAMIC_CALL));
	}

	/**
	 *
	 * @return void
	 */
	public function testParentInStaticClassClosure() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.StaticClosureParentClass.inc',ErrorConstants::TYPE_INCORRECT_DYNAMIC_CALL));
	}

	/**
	 *
	 * @return void
	 */
	public function testEnumCasesMethodExists() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.EnumCasesCall.inc', ErrorConstants::TYPE_UNKNOWN_METHOD));
	}

	/**
	 *
	 * @return void
	 */
	public function testEnumCasesMethodIsStatic() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.EnumCasesCall.inc', ErrorConstants::TYPE_INCORRECT_DYNAMIC_CALL));
	}
}
