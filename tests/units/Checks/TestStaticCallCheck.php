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

	/**
	 * `values()` is not an enum method: PHP provides `cases()` on every enum and
	 * `from()`/`tryFrom()` on backed enums, and nothing else. A call to it on an enum that
	 * declares no such method is undefined at runtime and must be reported as one.
	 *
	 * @return void
	 */
	public function testUndeclaredEnumValuesIsAnUnknownMethod() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.EnumValuesUndeclared.inc', ErrorConstants::TYPE_UNKNOWN_METHOD));
	}

	/**
	 * The same call must not ALSO be reported as a static-call problem, which is what
	 * happens when a non-static method is invented to satisfy it.
	 *
	 * @return void
	 */
	public function testUndeclaredEnumValuesIsNotReportedAsADynamicCall() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.EnumValuesUndeclared.inc', ErrorConstants::TYPE_INCORRECT_DYNAMIC_CALL));
	}
}
