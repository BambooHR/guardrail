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
		$this->markTestSkipped('Need to create .inc file for this test');
	}
	
	public function testRelatedTypesSharedInterface() {
		$output = $this->analyzeFileToOutput('.2.inc', [ErrorConstants::TYPE_AMBIGUOUS_METHOD_CALL]);
		$this->assertEquals(0, $output->getErrorCount(), "Should not error - ImplA|ImplB share CommonInterface");
	}
	
	public function testSingleType() {
		$this->markTestSkipped('Single type - no union, check not applicable');
	}
	
	public function testNullableType() {
		$this->markTestSkipped('Nullable type - not a union of classes, check not applicable');
	}
	
	// ========================================
	// Unrelated Types (SHOULD Error)
	// ========================================
	
	public function testUnrelatedClasses() {
		$output = $this->analyzeFileToOutput('.3.inc', [ErrorConstants::TYPE_AMBIGUOUS_METHOD_CALL]);
		$this->assertGreaterThan(0, $output->getErrorCount(), "Should error - ClassA|ClassB are unrelated");
	}
	
	public function testUnrelatedInterfaces() {
		$this->markTestSkipped('Need to create .inc file for this test');
	}
	
	public function testThreeUnrelatedTypes() {
		$this->markTestSkipped('Need to create .inc file for this test');
	}
	
	public function testMixedRelatedAndUnrelated() {
		$this->markTestSkipped('Need to create .inc file for this test');
	}
	
	// ========================================
	// Edge Cases
	// ========================================
	
	public function testWithMixedType() {
		$this->markTestSkipped('Mixed type - check not applicable');
	}
	
	public function testWithScalarTypes() {
		$this->markTestSkipped('Scalar types - check not applicable');
	}
	
	public function testComplexInheritanceHierarchy() {
		$this->markTestSkipped('Need to create .inc file for this test');
	}
}
