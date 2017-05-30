<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\CatchCheck;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Class TestCatchCheck
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class TestCatchCheck extends TestSuiteSetup {

	/**
	 * testMissingExceptionClass
	 *
	 * @return void
	 */
	public function testMissingExceptionClass() {
		$code = '<?php try { $something; } catch(MissingExceptionNameException $exception) { } ';
		$statements = $this->parseText($code);
		$this->checkClassEmitsErrorOnce(CatchCheck::class, $statements[0]->catches[0]);
	}

	/**
	 * testBaseExceptionCatch
	 *
	 * @return void
	 */
	public function testBaseExceptionCatch() {
		$code = '<?php try { $something; } catch(Exception $exception) { } ';
		$statements = $this->parseText($code);
		$this->checkClassEmitsErrorOnce(CatchCheck::class, $statements[0]->catches[0]);
	}
}