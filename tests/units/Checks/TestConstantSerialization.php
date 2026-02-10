<?php

namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Test that constant type inference works correctly through serialization/deserialization
 * in the JsonSymbolTable. This ensures array constants, magic constants, and string
 * concatenation expressions are properly typed.
 */
class TestConstantSerialization extends TestSuiteSetup
{
	/**
	 * Test that array constants are correctly typed as array, not string
	 *
	 * @return void
	 */
	public function testArrayConstantType() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.1.inc', ''));
	}

	/**
	 * Test that magic constants like __DIR__ are correctly typed as string
	 *
	 * @return void
	 */
	public function testMagicConstantType() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.2.inc', ''));
	}

	/**
	 * Test that string concatenation expressions are correctly typed as string
	 *
	 * @return void
	 */
	public function testStringConcatenationType() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.3.inc', ''));
	}
}
