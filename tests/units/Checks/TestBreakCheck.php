<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\BreakCheck;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Class TestBreakCheck
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class TestBreakCheck extends TestSuiteSetup {

	/**
	 * testBreakCheckInForeach
	 *
	 * @return void
	 */
	public function testBreakCheckInForeach() {
		$code = '<?php foreach ($foo as $bar) { break 3; } ';
		$statements = $this->parseText($code);
		$this->checkClassEmitsErrorOnce(BreakCheck::class, $statements[0]->stmts[0]);
	}

	/**
	 * testBreakCheckInForeachWithoutLoops
	 *
	 * @return void
	 */
	public function testBreakCheckInForeachWithoutLoops() {
		$code = '<?php foreach ($foo as $bar) { break; } ';
		$statements = $this->parseText($code);
		$this->checkClassNeverEmitsError(BreakCheck::class, $statements[0]->stmts[0]);
	}

	/**
	 * testContinueCheckInForeach
	 *
	 * @return void
	 */
	public function testContinueCheckInForeach() {
		$code = '<?php foreach ($foo as $bar) { continue 3; } ';
		$statements = $this->parseText($code);
		$this->checkClassEmitsErrorOnce(BreakCheck::class, $statements[0]->stmts[0]);
	}

	/**
	 * testContinueCheckInForeachWithoutLoops
	 *
	 * @return void
	 */
	public function testContinueCheckInForeachWithoutLoops() {
		$code = '<?php foreach ($foo as $bar) { continue; } ';
		$statements = $this->parseText($code);
		$this->checkClassNeverEmitsError(BreakCheck::class, $statements[0]->stmts[0]);
	}
}