<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Class TestClassMethodStringCheck
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class TestClassMethodStringCheck extends TestSuiteSetup {

	/**
	 * testValidMethodString
	 *
	 * @return void
	 * @rapid-unit Checks:ClassMethodStringCheck:Valid ClassName::class."@method" should not error
	 */
	public function testValidMethodString() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_UNKNOWN_METHOD_STRING));
	}

	/**
	 * testInvalidMethodString
	 *
	 * @return void
	 * @rapid-unit Checks:ClassMethodStringCheck:Invalid method in ClassName::class."@method" should error
	 */
	public function testInvalidMethodString() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.2.inc', ErrorConstants::TYPE_UNKNOWN_METHOD_STRING));
	}

	/**
	 * testStringWithoutAtSymbol
	 *
	 * @return void
	 * @rapid-unit Checks:ClassMethodStringCheck:String without @ prefix should not be checked
	 */
	public function testStringWithoutAtSymbol() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.3.inc', ErrorConstants::TYPE_UNKNOWN_METHOD_STRING));
	}

	/**
	 * testGetCheckNodeTypes
	 *
	 * @return void
	 * @rapid-unit Checks:ClassMethodStringCheck:getCheckNodeTypes returns correct node types
	 */
	public function testGetCheckNodeTypes() {
		$check = new \BambooHR\Guardrail\Checks\ClassMethodStringCheck(
			new \BambooHR\Guardrail\SymbolTable\InMemorySymbolTable('/'),
			$this->createMock(\BambooHR\Guardrail\Output\OutputInterface::class)
		);
		$types = $check->getCheckNodeTypes();
		$this->assertIsArray($types);
		$this->assertContains(\PhpParser\Node\Expr\BinaryOp\Concat::class, $types);
	}
}
