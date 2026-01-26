<?php

namespace BambooHR\Guardrail\Tests\Checks;

use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\SymbolTable\InMemorySymbolTable;
use BambooHR\Guardrail\Tests\TestSuiteSetup;
use BambooHR\Guardrail\Checks\BaseCheck;
use BambooHR\Guardrail\SymbolTable\SymbolTable;

class FakeOutput implements OutputInterface {

	public function emitError($className, $file, $line, $type, $message = '') {
		return $className . $file . $line . $type . $message;
	}

	public function output($verbose, $extraVerbose) {
		return;
	}

	public function ttyContent(string $content): string {
		return $content;
	}

	public function outputVerbose($string) {
		return;
	}

	public function outputExtraVerbose($string) {
		return;
	}

	public function incTests() {
		return;
	}

	public function getErrorCount() {
		return 0;
	}

	public function silenceType($name) {
		return;
	}

	public function resumeType($name) {
		return;
	}

	public function getErrorCounts() {
		return [];
	}

	public function isTTY(): bool {
		return false;
	}
}

class FakeSymbolTable extends SymbolTable {
	public function isDefinedClass($name) {
		return false;
	}

	public function updateClass(\PhpParser\Node\Stmt\ClassLike $class) {
		return;
	}

	public function removeFileFromIndex($name) {
		return;
	}

	public function addClass($name, \PhpParser\Node\Stmt\ClassLike $class, $file) {
		return;
	}

	public function addInterface($name, \PhpParser\Node\Stmt\Interface_ $interface, $file) {
		return;
	}

	public function getClassFile($className) {
		return "";
	}

	public function getTraitFile($traitName) {
		return "";
	}

	public function addTrait($name, \PhpParser\Node\Stmt\Trait_ $trait, $file) {
		return;
	}

	public function getInterfaceFile($interfaceName) {
		return "";
	}

	public function getFunctionFile($traitName) {
		return "";
	}

	public function getDefineFile($traitName) {
		return;
	}

	public function addDefine($name, \PhpParser\Node $define, $file) {
		return;
	}

	public function getClassesThatUseAnyTrait() {
		return [];
	}

	public function classExistsAnyNamespace($name) {
		return false;
	}
}

class FakeBaseCheck extends BaseCheck {

	public function __construct() {
		parent::__construct(new FakeSymbolTable("Test"), new FakeOutput());
	}

	public function getCheckNodeTypes() {
		return ['test'];
	}

	public function run($fileName, \PhpParser\Node $node, ?\PhpParser\Node\Stmt\ClassLike $inside = null, ?\BambooHR\Guardrail\Scope $scope = null) {
	return;
}
}

class TestBaseCheck extends TestSuiteSetup {
	public function testIncTests() {
		$check = new FakeBaseCheck();
		$this->assertNull($check->incTests());
	}

	public function testEmitErrorOnLine() {
		$check = new FakeBaseCheck();
		$file = "file";
		$line = 1;
		$class = "class";
		$message = "message";
		$expectedClass = "BambooHR\Guardrail\Tests\Checks\FakeBaseCheck";

		$result = $check->emitErrorOnLine($file, $line, $class, $message);

		$expected = $expectedClass . $file . $line . $class . $message;
		$this->assertEquals($expected, $result);

	}
	public function testEmitError() {
		$check = new FakeBaseCheck();
		$file = "file";
		$line = 1;
		$class = "class";
		$message = "message";
		$expectedClass = "BambooHR\Guardrail\Tests\Checks\FakeBaseCheck";

		$node = $this->getMockBuilder(\PhpParser\Node::class)->getMock();
		$node->expects($this->once())->method("getAttribute")->willReturn("");
		$node->expects($this->once())->method("getLine")->willReturn($line);

		$result = $check->emitError($file, $node, $class, $message);

		$expected = $expectedClass . $file . $line . $class . $message;
		$this->assertEquals($expected, $result);
	}

	public function testEmitErrorTrait() {
		$check = new FakeBaseCheck();

		$trait = "Test//One";
		$file = "file";
		$line = 1;
		$class = "class";
		$message = "message";
		$expectedClass = "BambooHR\Guardrail\Tests\Checks\FakeBaseCheck";

		$node = $this->getMockBuilder(\PhpParser\Node::class)->getMock();
		$node->expects($this->exactly(2))
			 ->method("getAttribute")
			 ->willReturnOnConsecutiveCalls($trait, $line);

		$node->expects($this->once())->method("getLine")->willReturn(42);

		$result = $check->emitError($file, $node, $class, $message);

		$expected = $expectedClass . $file . $line . $class . $message . " in imported code One:42";
		$this->assertEquals($expected, $result);
	}
}
