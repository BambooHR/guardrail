<?php

namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

class TestPropertyTypesCheck extends TestSuiteSetup {
	
	public function testCallablePropertyTypeNotAllowed() {
		$this->assertEquals(
			1,
			$this->runAnalyzerOnFile('.callable-not-allowed.inc', ErrorConstants::TYPE_ILLEGAL_PROPERTY_TYPE)
		);
	}
	
	public function testCallableInUnionTypeNotAllowed() {
		$this->assertEquals(
			1,
			$this->runAnalyzerOnFile('.callable-union.inc', ErrorConstants::TYPE_ILLEGAL_PROPERTY_TYPE)
		);
	}
	
	public function testNullableCallableNotAllowed() {
		$this->assertEquals(
			1,
			$this->runAnalyzerOnFile('.nullable-callable.inc', ErrorConstants::TYPE_ILLEGAL_PROPERTY_TYPE)
		);
	}
	
	public function testClosurePropertyAllowed() {
		$this->assertEquals(
			0,
			$this->runAnalyzerOnFile('.closure-allowed.inc', ErrorConstants::TYPE_ILLEGAL_PROPERTY_TYPE)
		);
	}
	
	public function testNonCallablePropertiesAllowed() {
		$this->assertEquals(
			0,
			$this->runAnalyzerOnFile('.non-callable.inc', ErrorConstants::TYPE_ILLEGAL_PROPERTY_TYPE)
		);
	}
	
	public function testVoidPropertyNotAllowed() {
		$this->assertEquals(
			1,
			$this->runAnalyzerOnFile('.void-not-allowed.inc', ErrorConstants::TYPE_ILLEGAL_PROPERTY_TYPE)
		);
	}
	
	public function testNeverPropertyNotAllowed() {
		$this->assertEquals(
			1,
			$this->runAnalyzerOnFile('.never-not-allowed.inc', ErrorConstants::TYPE_ILLEGAL_PROPERTY_TYPE)
		);
	}
	
	public function testTruePropertyNotAllowed() {
		$this->assertEquals(
			1,
			$this->runAnalyzerOnFile('.true-not-allowed.inc', ErrorConstants::TYPE_ILLEGAL_PROPERTY_TYPE)
		);
	}
	
	public function testFalsePropertyNotAllowed() {
		$this->assertEquals(
			1,
			$this->runAnalyzerOnFile('.false-not-allowed.inc', ErrorConstants::TYPE_ILLEGAL_PROPERTY_TYPE)
		);
	}
	
	public function testMultipleInvalidTypesInUnion() {
		$this->assertEquals(
			1,
			$this->runAnalyzerOnFile('.invalid-union.inc', ErrorConstants::TYPE_ILLEGAL_PROPERTY_TYPE)
		);
	}
}
