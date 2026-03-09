<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Test ambiguous method call detection for unrelated types in unions
 */
class TestAmbiguousMethodCall extends TestSuiteSetup {
	
	// ========================================
	// Related Types (Should NOT Error)
	// ========================================
	
	public function testRelatedTypesViaInheritance() {
		$output = $this->analyzeFileToOutput('.1.inc', [ErrorConstants::TYPE_AMBIGUOUS_METHOD_CALL]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - A|B|C are all related via inheritance");
	}
	
	public function testRelatedTypesViaInterface() {
		$output = $this->analyzeFileToOutput('.4.inc', [ErrorConstants::TYPE_AMBIGUOUS_METHOD_CALL]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - FooInterface|FooImpl are related");
	}
	
	public function testRelatedTypesSharedInterface() {
		$output = $this->analyzeFileToOutput('.2.inc', [ErrorConstants::TYPE_AMBIGUOUS_METHOD_CALL]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - ImplA|ImplB share CommonInterface");
	}
	
	public function testSingleType() {
		$output = $this->analyzeFileToOutput('.11.inc', [ErrorConstants::TYPE_AMBIGUOUS_METHOD_CALL]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - single type, no union");
	}
	
	public function testNullableType() {
		$output = $this->analyzeFileToOutput('.12.inc', [ErrorConstants::TYPE_AMBIGUOUS_METHOD_CALL]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - nullable type, not a union of classes (may emit null method call but not ambiguous)");
	}
	
	// ========================================
	// Unrelated Types (SHOULD Error)
	// ========================================
	
	public function testUnrelatedClasses() {
		$output = $this->analyzeFileToOutput('.3.inc', [ErrorConstants::TYPE_AMBIGUOUS_METHOD_CALL]);
		$this->assertGreaterThan(0, $output->getErrorCount(), "Should error - ClassA|ClassB are unrelated");
	}
	
	public function testUnrelatedInterfaces() {
		$output = $this->analyzeFileToOutput('.5.inc', [ErrorConstants::TYPE_AMBIGUOUS_METHOD_CALL]);
		$this->assertGreaterThan(0, $output->getErrorCount(), "Should error - InterfaceA|InterfaceB are unrelated");
	}
	
	public function testThreeUnrelatedTypes() {
		$output = $this->analyzeFileToOutput('.6.inc', [ErrorConstants::TYPE_AMBIGUOUS_METHOD_CALL]);
		$this->assertGreaterThan(0, $output->getErrorCount(), "Should error - TypeA|TypeB|TypeC are unrelated");
	}
	
	public function testMixedRelatedAndUnrelated() {
		$output = $this->analyzeFileToOutput('.7.inc', [ErrorConstants::TYPE_AMBIGUOUS_METHOD_CALL]);
		$this->assertGreaterThan(0, $output->getErrorCount(), "Should error - Unrelated is not related to Base|Child");
	}
	
	// ========================================
	// Edge Cases
	// ========================================
	
	public function testWithMixedType() {
		$output = $this->analyzeFileToOutput('.8.inc', [ErrorConstants::TYPE_AMBIGUOUS_METHOD_CALL]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - mixed is ignored in union");
	}
	
	public function testWithScalarTypes() {
		$output = $this->analyzeFileToOutput('.9.inc', [ErrorConstants::TYPE_AMBIGUOUS_METHOD_CALL]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - scalar types are ignored in union");
	}
	
	public function testComplexInheritanceHierarchy() {
		$output = $this->analyzeFileToOutput('.10.inc', [ErrorConstants::TYPE_AMBIGUOUS_METHOD_CALL]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - ConcreteA|ConcreteB share AbstractBase and BaseInterface");
	}
}
