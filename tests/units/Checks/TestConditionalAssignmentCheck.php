<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\ConditionalAssignmentCheck;
use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\SymbolTable\InMemorySymbolTable;
use BambooHR\Guardrail\Tests\TestSuiteSetup;
use PhpParser\Node\Stmt\If_;


/**
 * Class TestConditionalAssignmentCheck
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class TestConditionalAssignmentCheck extends TestSuiteSetup {
	public function testGetCheckNodeTypes() {
		$check = new ConditionalAssignmentCheck(
			new InMemorySymbolTable('/'),
			$this->createMock(OutputInterface::class)
		);
		$types = $check->getCheckNodeTypes();
		$this->assertIsArray($types);
		$this->assertContains(If_::class, $types);
	}

	public function testConditionalAssignmentEmitsError() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_CONDITIONAL_ASSIGNMENT));
	}

	public function testConditionalAssignmentDoesNotEmitError() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.2.inc', ErrorConstants::TYPE_CONDITIONAL_ASSIGNMENT));
	}
}
