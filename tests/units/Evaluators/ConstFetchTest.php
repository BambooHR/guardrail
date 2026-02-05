<?php

namespace BambooHR\Guardrail\Tests\Evaluators;

use BambooHR\Guardrail\Evaluators\Expression\ConstFetch;
use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use PhpParser\Node\Expr\ConstFetch as ConstFetchNode;
use PhpParser\Node\Name;
use PHPUnit\Framework\TestCase;

class ConstFetchTest extends TestCase {
	private SymbolTable $symbolTable;
	private ScopeStack $scopeStack;
	private ConstFetch $evaluator;

	public function setUp(): void {
		$this->symbolTable = $this->createMock(SymbolTable::class);
		$this->scopeStack = $this->createMock(ScopeStack::class);
		$this->evaluator = new ConstFetch();
	}

	/**
	 * @dataProvider runtimeConstantTypeProvider
	 */
	public function testRuntimeConstantTypeInference($constantName, $expectedType) {
		if (!defined($constantName)) {
			$this->markTestSkipped("Constant $constantName is not defined in this PHP runtime");
		}

		$node = new ConstFetchNode(new Name($constantName));
		$result = $this->evaluator->onExit($node, $this->symbolTable, $this->scopeStack);

		$this->assertInstanceOf(\PhpParser\Node\Identifier::class, $result);
		$this->assertEquals($expectedType, (string)$result);
	}

	public static function runtimeConstantTypeProvider(): array {
		return [
			'FILTER_VALIDATE_EMAIL is int' => ['FILTER_VALIDATE_EMAIL', 'int'],
			'FILTER_FLAG_NONE is int' => ['FILTER_FLAG_NONE', 'int'],
			'PHP_VERSION is string' => ['PHP_VERSION', 'string'],
			'PHP_OS is string' => ['PHP_OS', 'string'],
			'PHP_INT_MAX is int' => ['PHP_INT_MAX', 'int'],
			'PHP_FLOAT_EPSILON is float' => ['PHP_FLOAT_EPSILON', 'float'],
		];
	}

	public function testBooleanConstants() {
		$trueNode = new ConstFetchNode(new Name('true'));
		$result = $this->evaluator->onExit($trueNode, $this->symbolTable, $this->scopeStack);
		$this->assertEquals('true', (string)$result);

		$falseNode = new ConstFetchNode(new Name('false'));
		$result = $this->evaluator->onExit($falseNode, $this->symbolTable, $this->scopeStack);
		$this->assertEquals('false', (string)$result);
	}

	public function testNullConstant() {
		$nullNode = new ConstFetchNode(new Name('null'));
		$result = $this->evaluator->onExit($nullNode, $this->symbolTable, $this->scopeStack);
		$this->assertEquals('null', (string)$result);
	}
}
