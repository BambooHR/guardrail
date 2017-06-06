<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\BacktickOperatorCheck;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Class TestBackTickOperatorCheck
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class TestBackTickOperatorCheck extends TestSuiteSetup {

	/**
	 * testBackTicksThrowError
	 *
	 * @return void
	 */
	public function testBackTicksThrowError() {
		$code = '<?php echo `ping -n 3 {$host}`;';
		$statements = $this->parseText($code);
		$this->checkClassEmitsErrorOnce(BacktickOperatorCheck::class, $statements[0]->exprs[0]);
	}

	/**
	 * testBackTicksNotThrownInComment
	 *
	 * @return void
	 */
	public function testBackTicksNotThrownInComment() {
		$code = '<?php //`ping -n 3 {$host}`';
		$statements = $this->parseText($code);
		$this->checkClassNeverEmitsError(BacktickOperatorCheck::class, $statements[0]);
	}
}