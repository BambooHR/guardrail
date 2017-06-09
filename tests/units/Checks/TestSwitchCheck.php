<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Checks\SwitchCheck;
use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\SymbolTable\InMemorySymbolTable;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Class TestSwitchCheck
 */
class TestSwitchCheck extends TestSuiteSetup {

	/**
	 * testAllBranchesExit
	 *
	 * @return void
	 * @rapid-unit Check:SwitchCheck:Detects when all branches in a switch contains an safe exit
	 */
	public function testAllBranchesExitReturnsTrue() {
		$testFile = dirname(__FILE__) . '/TestData/' . basename(__FILE__, '.php') . '.1.inc';
		$this->assertEquals(0, $this->runAnalyzerOnFile($testFile, ErrorConstants::TYPE_MISSING_BREAK));
		$code = file_get_contents($testFile);

		$builder = $this->getMockBuilder(OutputInterface::class);
		$output = $builder
			->setMethods(["emitError"])
			->getMockForAbstractClass();
		$emptyTable = new InMemorySymbolTable(__DIR__);
		$stmts = $this->parseText($code);
		$check = new SwitchCheck($emptyTable, $output);
		$this->assertTrue($check->allBranchesExit( $stmts ) );
	}

	/**
	 * testMissingBreak
	 *
	 * @return void
	 * @rapid-unit Checks:SwitchCheck:Detects when a switch case is missing a break statement
	 */
	public function testMissingBreak() {
		$testFile = dirname(__FILE__) . '/TestData/' . basename(__FILE__, '.php') . '.2.inc';
		$this->assertEquals(2, $this->runAnalyzerOnFile($testFile, ErrorConstants::TYPE_MISSING_BREAK));
	}

	/**
	 * testGoodSwitch
	 *
	 * @return void
	 * @rapid-unit Checks:SwitchCase:Does not emit an error on a valid switch statement
	 */
	public function testGoodSwitch() {
		$testFile = dirname(__FILE__) . '/TestData/' . basename(__FILE__, '.php') . '.3.inc';
		$this->assertEquals(0, $this->runAnalyzerOnFile($testFile, ErrorConstants::TYPE_MISSING_BREAK));
	}
}