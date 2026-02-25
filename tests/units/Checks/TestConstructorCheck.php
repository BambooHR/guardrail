<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\ConstructorCheck;
use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\SymbolTable\InMemorySymbolTable;
use BambooHR\Guardrail\Tests\TestSuiteSetup;
use PhpParser\Node\Stmt\ClassMethod;

/**
 * Class TestConstructorCheck
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class TestConstructorCheck extends TestSuiteSetup {

	/**
	 * @return void
	 */
	public function testGetCheckNodeTypes() {
		$check = new ConstructorCheck(
			new InMemorySymbolTable('/'),
			$this->createMock(OutputInterface::class)
		);
		$types = $check->getCheckNodeTypes();
		$this->assertIsArray($types);
		$this->assertContains(ClassMethod::class, $types);
	}

	/**
	 * @return void
	 */
	public function testDoesNotErrorWhenParentConstructorCalled() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.2.inc', ErrorConstants::TYPE_MISSING_CONSTRUCT));
	}

	/**
	 * @return void
	 */
	public function testDoesNotErrorWhenParentConstructorAbstract() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.3.inc', ErrorConstants::TYPE_MISSING_CONSTRUCT));
	}

	/**
	 * testCheckMissingCallToParentConstructor
	 *
	 * @return void
	 * @rapid-unit Checks:ConstructorCheck:Catches overriding constructor that does not call the parent constructor
	 */
	public function testCheckMissingCallToParentConstructor() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_MISSING_CONSTRUCT));
	}
}