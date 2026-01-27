<?php

namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\SymbolTable\InMemorySymbolTable;
use BambooHR\Guardrail\Tests\TestSuiteSetup;
use BambooHR\Guardrail\Checks\BaseCheck;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\Checks\ErrorConstants;

class TestBaseCheck extends TestSuiteSetup {

	private $symbolTable;
	private $output;
	private $check;
	private $node;

	private function setupMocks() {
		$this->node = $this->getMockBuilder(\PhpParser\Node::class)->getMock();
		$this->symbolTable = $this->createMock(SymbolTable::class);
		$this->output = $this->createMock(OutputInterface::class);
		$this->check = $this->getMockBuilder(BaseCheck::class)
			->setConstructorArgs([$this->symbolTable, $this->output])
			->getMockForAbstractClass();
	}

	private function unsetMocks() {
		$this->symbolTable = null;
		$this->output = null;
		$this->check = null;
	}

	public function testIncTests() {
		$this->setupMocks();
		$this->output->expects($this->once())->method('incTests');

		$this->assertNull($this->check->incTests());
		$this->unsetMocks();
	}

	public function testEmitErrorOnLine() {
		$this->setupMocks();
		$file = "file";
		$line = 1;
		$class = "class";
		$message = "message";
		$className = get_class($this->check);

		$emitErrorResponse = "Error emitted";

		$this->output->expects($this->once())->method('emitError')->with($className, $file, $line, $class, $message)->willReturn($emitErrorResponse);

		$result = $this->check->emitErrorOnLine($file, $line, $class, $message);

		$this->assertEquals($emitErrorResponse, $result);

		$this->unsetMocks();
	}
	public function testEmitError() {
		$this->setupMocks();
		$file = "file";
		$line = 1;
		$class = "class";
		$message = "message";

		$className = get_class($this->check);

		$emitErrorResponse = "Error emitted";

		$this->output->expects($this->once())->method('emitError')->with($className, $file, $line, $class, $message)->willReturn($emitErrorResponse);

		$this->node->expects($this->once())->method("getAttribute")->willReturn("");
		$this->node->expects($this->once())->method("getLine")->willReturn($line);

		$result = $this->check->emitError($file, $this->node, $class, $message);

		$this->assertEquals($emitErrorResponse, $result);

		$this->unsetMocks();
	}

	public function testEmitErrorTrait() {
		$this->setupMocks();

		$trait = "Test//One::testTrait";
		$file = "file";
		$line = 1;
		$class = "class";
		$message = "message";
		$className = get_class($this->check);

		$emitErrorResponse = "Error emitted";
		$sentMessage = "message in imported code trait:42";
		$sentTrait = "Test/One::testTrait";

		$this->symbolTable->expects($this->once())->method('removeBasePath')->with($sentTrait)->willReturn("trait");
		$this->output->expects($this->once())->method('emitError')->with($className, $file, $line, $class, $sentMessage)->willReturn($emitErrorResponse);
		$this->node->expects($this->exactly(2))
			 ->method("getAttribute")
			 ->willReturnOnConsecutiveCalls($trait, $line);
		$this->node->expects($this->once())->method("getLine")->willReturn(42);

		$result = $this->check->emitError($file, $this->node, $class, $message);

		$this->assertEquals($emitErrorResponse, $result);

		$this->unsetMocks();
	}

	public function testTraitImport() {
		$this->assertEquals(1, $this->runAnalyzerOnFile('-trait.inc', ErrorConstants::TYPE_SIGNATURE_RETURN), "Failed change Type Inference to non null" );
	}
}
