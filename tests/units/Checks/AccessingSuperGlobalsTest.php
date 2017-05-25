<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\AccessingSuperGlobalsCheck;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Class AccessingSuperGlobalsCheckTest
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class AccessingSuperGlobalsCheckTest extends TestSuiteSetup {

	/**
	 * testRunAccessingSuperGlobalGlobalExpressions
	 *
	 * @return void
	 * @rapid-unit Checks:AccessingSuperGlobals:Calling the $GLOBALS array emits an error
	 */
	public function testRunAccessingSuperGlobalGlobalExpressions() {
		$code = '<?php $GLOBALS["check"]; ';
		$statements = $this->parseText($code);
		$this->checkClassEmitsErrorOnce(AccessingSuperGlobalsCheck::class, $statements[0]->var);
	}

	/**
	 * testRunAccessingSuperGlobalGlobalVariables
	 *
	 * @return void
	 * @rapid-unit Checks:AccessingSuperGlobals:Calling `global $var` emits an error
	 */
	public function testRunAccessingSuperGlobalGlobalVariables() {
		$code = '<?php global $check; ';
		$statements = $this->parseText($code);
		$this->checkClassEmitsErrorOnce(AccessingSuperGlobalsCheck::class, $statements[0]);
	}

	/**
	 * testRunAccessingSuperGlobalVariableOnly
	 *
	 * @return void
	 * @rapid-unit Checks:AccessingSuperGlobals:Assigning $GLOBALS emits an error
	 */
	public function testRunAccessingSuperGlobalVariableOnly() {
		$code = '<?php $something = $GLOBALS;';
		$statements = $this->parseText($code);
		$this->checkClassEmitsErrorOnce(AccessingSuperGlobalsCheck::class, $statements[0]->expr);
	}
}