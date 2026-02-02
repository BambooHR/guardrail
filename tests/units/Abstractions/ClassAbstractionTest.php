<?php

namespace BambooHR\Guardrail\Tests\Abstractions;

use BambooHR\Guardrail\Abstractions\AttributeAbstraction;
use BambooHR\Guardrail\Abstractions\ClassAbstraction;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Const_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\EnumCase;
use PHPUnit\Framework\TestCase;

class ClassAbstractionTest extends TestCase {
	public function testGetAttributesReturnsAttributes() {
		$classAbstraction = new ClassAbstraction(
			new Class_('MyClass', [
				'attrGroups' => [
					new AttributeGroup([
						new Attribute(new Name('MyAttribute'))
					])
				]
			])
		);

		$this->assertEquals(
			[
				new AttributeAbstraction(
					new Attribute(new Name('MyAttribute'))
				)
			],
			$classAbstraction->getAttributes()
		);
	}

	public function testGetAttributesReturnsEmptyArrayWhenNoAttributes() {
		$classAbstraction = new ClassAbstraction(new Class_('MyClass'));
		$this->assertEquals([], $classAbstraction->getAttributes());
	}

	public function testGetConstantValueExpressionReturnsExpression() {
		$constantValueExpression = new String_('my_value');
		$classAbstraction = new ClassAbstraction(
			new Class_('MyClass', [
				'stmts' => [
					new ClassConst([
						new Const_('MY_CONST', $constantValueExpression)
					])
				]
			])
		);
		$this->assertSame($constantValueExpression, $classAbstraction->getConstantValueExpression('MY_CONST'));
	}

	public function testGetConstantValueExpressionReturnsNullForNonExistentConstant() {
		$classAbstraction = new ClassAbstraction(new Class_('MyClass'));
		$this->assertNull($classAbstraction->getConstantValueExpression('NON_EXISTENT'));
	}

	public function testGetConstantValueExpressionForBackedEnum() {
		$caseValue = new String_('B');
		$enum = new Enum_('MyEnum', [
			'scalarType' => new Identifier('string'),
			'stmts' => [
				new EnumCase('MyCase', $caseValue)
			]
		]);
		$classAbstraction = new ClassAbstraction($enum);
		$this->assertSame($caseValue, $classAbstraction->getConstantValueExpression('MyCase'));
	}

	public function testGetConstantValueExpressionForPureEnum() {
		$enum = new Enum_('MyPureEnum', [
			'stmts' => [
				new EnumCase('MyCase', null)
			]
		]);
		$enum->namespacedName = new Name('MyPureEnum');
		$classAbstraction = new ClassAbstraction($enum);

		$expected = new ClassConstFetch(
			new Name('MyPureEnum'),
			new Identifier('MyCase')
		);

		$this->assertEquals($expected, $classAbstraction->getConstantValueExpression('MyCase'));
	}

	public function testGetConstantValueExpressionForConstantInEnum() {
		$constantValueExpression = new String_('my_value');
		$enum = new Enum_('MyEnum', [
			'stmts' => [
				new ClassConst([
					new Const_('MY_CONST', $constantValueExpression)
				]),
				new EnumCase('MyCase', new String_('C'))
			]
		]);
		$classAbstraction = new ClassAbstraction($enum);
		$this->assertSame($constantValueExpression, $classAbstraction->getConstantValueExpression('MY_CONST'));
	}
}