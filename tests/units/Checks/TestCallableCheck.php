<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\CallableCheck;
use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\SymbolTable\InMemorySymbolTable;
use BambooHR\Guardrail\Tests\TestSuiteSetup;
use PhpParser\Node;

/**
 * Class TestCallableCheck
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class TestCallableCheck extends TestSuiteSetup {


	/**
	 * @return array{OutputInterface, InMemorySymbolTable}
	 */
	private function setupMocks() {
		$output = $this->getMockBuilder(OutputInterface::class)->getMockForAbstractClass();
		$symbolTable = new InMemorySymbolTable(__DIR__);
		return [$output, $symbolTable];
	}
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
	 * @rapid-unit Checks:CallableCheck:Returns empty array for node types since it is embedded in other checks
	 */
	public function testGetCheckNodeTypesReturnsEmptyArray() {
		list($output, $symbolTable) = $this->setupMocks();
		$check = new CallableCheck($symbolTable, $output);
		$this->assertEquals([], $check->getCheckNodeTypes());
	}

	/**
	 * @return void
	 * @rapid-unit Checks:CallableCheck:Emits error when callable array method does not exist on class
	 */
	public function testUndefinedCallableMethod() {
		$output = $this->getMockBuilder(OutputInterface::class)
			->onlyMethods(['emitError'])
			->getMockForAbstractClass();
		$output->expects($this->once())
			->method('emitError')
				->with(
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->stringContains("methodThatDoesNotExist")
			);
		$symbolTable = new InMemorySymbolTable(__DIR__);
		$check = new CallableCheck($symbolTable, $output);

		$array = new Node\Expr\Array_([
			new Node\Expr\ArrayItem(new Node\Scalar\String_("Exception")),
			new Node\Expr\ArrayItem(new Node\Scalar\String_("methodThatDoesNotExist"))
		]);
		$check->checkClassType("Exception", __FILE__, $array);

	}
	/**
	 * @return void
	 * @rapid-unit Checks:CallableCheck:Strips leading backslash from function name before lookup
	 */
	public function testFunctionNameStripsLeadingBackslash() {
		$output = $this->getMockBuilder(OutputInterface::class)
			->onlyMethods(['emitError'])
			->getMockForAbstractClass();
		
		// Verify error message contains "someFunction" (stripped), not "\\someFunction"
		$output->expects($this->once())
			->method('emitError')
			->with(
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->stringContains("someFunction")  // backslash was stripped
			);
		
		$symbolTable = new InMemorySymbolTable(__DIR__);
		$check = new CallableCheck($symbolTable, $output);

		$node = new Node\Scalar\String_("\\someFunction");
		$check->run(__FILE__, $node, null, null);
	}

	/**
	 * @return void
	 * @rapid-unit Checks:CallableCheck:Emits error when callable string method does not exist on class
	 */
	public function testUndefinedCallableStringMethod() {
		$output = $this->getMockBuilder(OutputInterface::class)
			->onlyMethods(['emitError'])
			->getMockForAbstractClass();
		
		$output->expects($this->once())
			->method('emitError')
			->with(
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->stringContains("Exception::someFunction") 
			);
		
		$symbolTable = new InMemorySymbolTable(__DIR__);
		$check = new CallableCheck($symbolTable, $output);

		$node = new Node\Scalar\String_("Exception::someFunction");
		$check->run(__FILE__, $node, null, null);
	}

	/**
	 * @return void
	 * @rapid-unit Checks:CallableCheck:Emits error when array callable with inferred type references undefined method
	 */
	public function testArrayCallableWithInferredType() {
		$output = $this->getMockBuilder(OutputInterface::class)
			->onlyMethods(['emitError'])
			->getMockForAbstractClass();
		
		$output->expects($this->once())
			->method('emitError')
			->with(
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->stringContains("methodThatDoesNotExist")
			);
		
		$symbolTable = new InMemorySymbolTable(__DIR__);
		$check = new CallableCheck($symbolTable, $output);
		
		// Create a variable node with inferred type attribute
		$objectNode = new Node\Expr\Variable('obj');
		$objectNode->setAttribute(\BambooHR\Guardrail\TypeComparer::INFERRED_TYPE_ATTR, new Node\Name('Exception'));
		
		$array = new Node\Expr\Array_([
			new Node\Expr\ArrayItem($objectNode),
			new Node\Expr\ArrayItem(new Node\Scalar\String_("methodThatDoesNotExist"))
		]);
		
		$check->run(__FILE__, $array, null, null);
	}

	/**
	 * @return void
	 * @rapid-unit Checks:CallableCheck:Emits error when array callable with ClassConstFetch references undefined method
	 */
	public function testArrayCallableWithClassConstFetch() {
		$output = $this->getMockBuilder(OutputInterface::class)
			->onlyMethods(['emitError'])
			->getMockForAbstractClass();
		
		$output->expects($this->once())
			->method('emitError')
			->with(
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->stringContains("methodThatDoesNotExist")
			);
		
		$symbolTable = new InMemorySymbolTable(__DIR__);
		$check = new CallableCheck($symbolTable, $output);
			
		$array = new Node\Expr\Array_([
			new Node\Expr\ArrayItem(new Node\Expr\ClassConstFetch(new Node\Name('Exception'), new Node\Identifier('class'))),
			new Node\Expr\ArrayItem(new Node\Scalar\String_("methodThatDoesNotExist"))
		]);
		
		$check->run(__FILE__, $array, null, null);

	}
}
