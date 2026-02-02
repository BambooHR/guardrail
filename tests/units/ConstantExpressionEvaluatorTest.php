<?php

use BambooHR\Guardrail\Abstractions\ReflectedClass;
use BambooHR\Guardrail\ConstantExpressionEvaluator;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use PhpParser\Node\Expr\BinaryOp\BitwiseOr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name\FullyQualified;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConstantExpressionEvaluatorTest extends TestCase {
	private MockObject $symbolTable;
	private ConstantExpressionEvaluator $evaluator;

	public function setUp(): void {
		$this->symbolTable = $this->createMock(SymbolTable::class);
		$this->evaluator = new ConstantExpressionEvaluator($this->symbolTable);
	}

	public function testBasicClassConstantFetchEvaluation(): void {
		$this->symbolTable
			 ->expects($this->once())
			 ->method('getAbstractedClass')
			 ->with("Attribute")
			 ->willReturn(new ReflectedClass(new ReflectionClass(Attribute::class)));

		$this->assertEquals(
			2,
			$this->evaluator->evaluate(
				new ClassConstFetch(
					new FullyQualified('Attribute'),
					new Identifier('TARGET_FUNCTION')
				)
			)
		);
	}

	public function testClassConstantEvaluation(): void {
		$this->assertEquals(
			'MyClass',
			$this->evaluator->evaluate(
				new ClassConstFetch(
					new FullyQualified('MyClass'),
					new Identifier('class')
				)
			)
		);
	}

	public function testBitwiseOrEvaluation(): void {
		$this->symbolTable
			->expects($this->exactly(2))
			->method('getAbstractedClass')
			->with("Attribute")
			->willReturn(new ReflectedClass(new ReflectionClass(Attribute::class)));

		$this->assertEquals(
			6,
			$this->evaluator->evaluate(
				new BitwiseOr(
					new ClassConstFetch(
						new FullyQualified('Attribute'),
						new Identifier('TARGET_FUNCTION')
					),
					new ClassConstFetch(
						new FullyQualified('Attribute'),
						new Identifier('TARGET_METHOD')
					)
				)
			)
		);
	}
}
