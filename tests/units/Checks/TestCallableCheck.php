<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\CallableCheck;
use BambooHR\Guardrail\Tests\TestSuiteSetup;
use PhpParser\Node;

/**
 * Class TestCallableCheck
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class TestCallableCheck extends TestSuiteSetup {

	/**
	 * @return void
	 * @rapid-unit Checks:CallableCheck:Emits error when callable string references unknown function
	 */
	public function testUnknownFunctionCallable() {
		$node = new Node\Scalar\String_("unknownFunction");
		$this->checkClassEmitsErrorOnce(CallableCheck::class, $node);
	}

	/**
	 * @return void
	 * @rapid-unit Checks:CallableCheck:Emits error when callable string references unknown class
	 */
	public function testUnknownClassInStringCallable() {
		$node = new Node\Scalar\String_("UnknownClass::someMethod");
		$this->checkClassEmitsErrorOnce(CallableCheck::class, $node);
	}

	/**
	 * @return void
	 * @rapid-unit Checks:CallableCheck:Emits error when callable array has wrong number of elements
	 */
	public function testCallableArrayWrongCount() {
		$node = new Node\Expr\Array_([
			new Node\Expr\ArrayItem(new Node\Scalar\String_("OnlyOneElement"))
		]);
		$this->checkClassEmitsErrorOnce(CallableCheck::class, $node);
	}

	/**
	 * @return void
	 * @rapid-unit Checks:CallableCheck:Emits error when callable array references unknown class
	 */
	public function testUnknownClassInArrayCallable() {
		$node = new Node\Expr\Array_([
			new Node\Expr\ArrayItem(new Node\Scalar\String_("UnknownClass")),
			new Node\Expr\ArrayItem(new Node\Scalar\String_("someMethod"))
		]);
		$this->checkClassEmitsErrorOnce(CallableCheck::class, $node);
	}

	/**
	 * @return void
	 * @rapid-unit Checks:CallableCheck:Does not emit error for closure callable
	 */
	public function testClosureCallableNoError() {
		$node = new Node\Expr\Closure();
		$this->checkClassNeverEmitsError(CallableCheck::class, $node);
	}
}
