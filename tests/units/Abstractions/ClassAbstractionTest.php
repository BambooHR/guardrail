<?php

namespace BambooHR\Guardrail\Tests\Abstractions;

use BambooHR\Guardrail\Abstractions\AttributeAbstraction;
use BambooHR\Guardrail\Abstractions\ClassAbstraction;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Const_;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PHPUnit\Framework\TestCase;

class ClassAbstractionTest extends TestCase
{
	public function testGetAttributesReturnsAttributes()
	{
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

	public function testGetAttributesReturnsEmptyArrayWhenNoAttributes()
	{
		$classAbstraction = new ClassAbstraction(new Class_('MyClass'));
		$this->assertEquals([], $classAbstraction->getAttributes());
	}

	public function testGetConstantValueExpressionReturnsExpression()
	{
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

	public function testGetConstantValueExpressionReturnsNullForNonExistentConstant()
	{
		$classAbstraction = new ClassAbstraction(new Class_('MyClass'));
		$this->assertNull($classAbstraction->getConstantValueExpression('NON_EXISTENT'));
	}
}