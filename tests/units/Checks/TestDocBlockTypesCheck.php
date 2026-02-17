<?php namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Checks\DocBlockTypesCheck;
use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\SymbolTable\InMemorySymbolTable;
use BambooHR\Guardrail\Tests\TestSuiteSetup;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\PropertyProperty;

/**
 * Class TestDocBlockTypesCheck
 *
 * @package BambooHR\Guardrail\Tests\Checks
 */
class TestDocBlockTypesCheck extends TestSuiteSetup {
	public function testGetCheckNodeTypes() {
		$check = new DocBlockTypesCheck(
			new InMemorySymbolTable('/'),
			$this->createMock(OutputInterface::class)
		);
		$types = $check->getCheckNodeTypes();
		$this->assertContains(Function_::class, $types);
		$this->assertContains(ClassMethod::class, $types);
		$this->assertContains(PropertyProperty::class, $types);
	}

	public function testCheckOrEmitReportsTypeKeyword() {
		$output = $this->createMock(OutputInterface::class);
		$output->expects($this->exactly(2))
			->method('emitError')
			->with(
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->equalTo(ErrorConstants::TYPE_DOC_BLOCK_TYPE),
				$this->anything()
			);

		$check = new DocBlockTypesCheck(new InMemorySymbolTable('/'), $output);
		$node = $this->parseText('<?php function foo() {}')[0];
		$check->checkOrEmit('type|Foo\\type', 'file.php', $node, ErrorConstants::TYPE_DOC_BLOCK_RETURN, 'message');
	}

	public function testCheckOrEmitReportsUnknownClass() {
		$output = $this->createMock(OutputInterface::class);
		$output->expects($this->once())
			->method('emitError')
			->with(
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->equalTo(ErrorConstants::TYPE_DOC_BLOCK_VAR),
				$this->anything()
			);

		$check = new DocBlockTypesCheck(new InMemorySymbolTable('/'), $output);
		$node = $this->parseText('<?php function foo() {}')[0];
		$check->checkOrEmit('MissingClass', 'file.php', $node, ErrorConstants::TYPE_DOC_BLOCK_VAR, 'message');
	}

	public function testCheckOrEmitSkipsScalarAndDefinedClass() {
		$output = $this->createMock(OutputInterface::class);
		$output->expects($this->never())->method('emitError');

		$symbolTable = $this->createMock(InMemorySymbolTable::class);
		$symbolTable->method('isDefinedClass')->with('App\\KnownClass')->willReturn(true);

		$check = new DocBlockTypesCheck($symbolTable, $output);
		$node = $this->parseText('<?php function foo() {}')[0];
		$check->checkOrEmit('int|App\\KnownClass', 'file.php', $node, ErrorConstants::TYPE_DOC_BLOCK_VAR, 'message');
	}
}
