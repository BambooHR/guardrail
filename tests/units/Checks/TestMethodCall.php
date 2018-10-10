<?php

namespace BambooHR\Guardrail\Tests\units\Checks;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Tests\TestSuiteSetup;

/**
 * Class TestMethodCall
 *
 * @package BambooHR\Guardrail\Tests\units\Checks
 */
class TestMethodCall extends TestSuiteSetup {
	/**
	 * Test empty then referencing a method on a potentially null object.
	 *
	 * If the return type hint on the method is removed, guardrail doesn't fail. If using $obj && $obj->get() it also doesn't fail. It only fails
	 * when using empty($obj) || $obj->get().
	 *
	 * @return void
	 */
	public function testEmptyAndThenReferencingMethodOnPotentiallyNullObject() {
		$this->assertEquals(0, $this->runAnalyzerOnFile('.1.inc', ErrorConstants::TYPE_NULL_DEREFERENCE));
	}
}