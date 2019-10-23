<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Class TestUnusedPrivateMemberVariableCheck
 * @package BambooHR\Guardrail\Tests\Checks
 */
class TestUnusedPrivateMemberVariableCheck extends TestSuiteSetup {

	/**
	 * testUnreachableCodeAfterIfConditional
	 *
	 * @return void
	 * @rapid-unit Checks:UnusedPrivateMemberVariable:Catches private member variables that are not used in the class
	 */
	public function testUnusedPrivateMemberVariable() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_UNUSED_PROPERTY));
	}

//	/**
//	 * testUnusedPrivateMemberVariableIgnoresComments
//	 *
//	 * @return void
//	 * @rapid-unit Checks:UnusedPrivateMemberVariable:Catches private member variables that are not used in the class
//	 */
//	public function testUnusedPrivateMemberVariableIgnoresComments() {
//		$this->assertEquals(2, $this->runAnalyzerOnFile('.2.inc', ErrorConstants::TYPE_UNUSED_PROPERTY));
//	}
//
//	/**
//	 * testUnusedPrivateMemberVariableIgnoresPublicAndProtected
//	 *
//	 * @return void
//	 * @rapid-unit Checks:UnusedPrivateMemberVariable:Catches private member variables that are not used in the class
//	 */
//	public function testUnusedPrivateMemberVariableIgnoresPublicAndProtected() {
//		$this->assertEquals(2, $this->runAnalyzerOnFile('.3.inc', ErrorConstants::TYPE_UNUSED_PROPERTY));
//	}
//
//	/**
//	 * testUnusedPrivateMemberVariableSkipsUsedVariables
//	 *
//	 * @return void
//	 * @rapid-unit Checks:UnusedPrivateMemberVariable:Catches private member variables that are not used in the class
//	 */
//	public function testUnusedPrivateMemberVariableSkipsUsedVariables() {
//		$this->assertEquals(0, $this->runAnalyzerOnFile('.4.inc', ErrorConstants::TYPE_UNUSED_PROPERTY));
//	}
//
//	/**
//	 * testUnusedPrivateMemberVariableSkipsWhenAssigned
//	 *
//	 * @return void
//	 * @rapid-unit Checks:UnusedPrivateMemberVariable:Catches private member variables that are not used in the class
//	 */
//	public function testUnusedPrivateMemberVariableSkipsWhenAssigned() {
//		$this->assertEquals(0, $this->runAnalyzerOnFile('.5.inc', ErrorConstants::TYPE_UNUSED_PROPERTY));
//	}
}