<?php

namespace BambooHR\Guardrail\Tests\Abstractions;

use BambooHR\Guardrail\Abstractions\ReflectedAttribute;
use BambooHR\Guardrail\Abstractions\ReflectedClass;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\DNumber;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Attribute;

#[Attribute]
class TestAttributeForReflectedTest {
	public function __construct(public string $value)
	{
	}
}

#[TestAttributeForReflectedTest("hello")]
class ClassWithAttributeForReflectedTest {}

class ClassWithoutAttributesForReflectedTest {}

class ClassWithConstantsForReflectedTest {
	const TEST_STRING = "string value";
	const TEST_INT = 42;
	const TEST_FLOAT = 3.14;
	const TEST_BOOL_TRUE = true;
	const TEST_BOOL_FALSE = false;
	const TEST_NULL = null;
	const TEST_ARRAY = ["foo" => "bar", 1 => 2];
}

class ReflectedClassTest extends TestCase {

	public function testGetAttributes() {
		$reflectionClass = new ReflectionClass(ClassWithAttributeForReflectedTest::class);
		$reflectedClass = new ReflectedClass(new ReflectionClass(ClassWithAttributeForReflectedTest::class));
		$this->assertEquals(
			[new ReflectedAttribute($reflectionClass->getAttributes()[0])],
			$reflectedClass->getAttributes()
		);
	}

	public function testGetAttributesReturnsEmptyArray() {
		$reflectedClass = new ReflectedClass(new ReflectionClass(ClassWithoutAttributesForReflectedTest::class));
		$this->assertEquals([], $reflectedClass->getAttributes());
	}

	/**
	 * @dataProvider constantValueProvider
	 */
	public function testGetConstantValueExpression($constantName, $expectedNode) {
		$reflectedClass = new ReflectedClass(new ReflectionClass(ClassWithConstantsForReflectedTest::class));
		$this->assertEquals($expectedNode, $reflectedClass->getConstantValueExpression($constantName));
	}

	public function constantValueProvider(): array {
		return [
			'string' => ['TEST_STRING', new String_("string value")],
			'integer' => ['TEST_INT', new LNumber(42)],
			'float' => ['TEST_FLOAT', new DNumber(3.14)],
			'true' => ['TEST_BOOL_TRUE', new ConstFetch(new Name('true'))],
			'false' => ['TEST_BOOL_FALSE', new ConstFetch(new Name('false'))],
			'null' => ['TEST_NULL', new ConstFetch(new Name('null'))],
			'array' => ['TEST_ARRAY', new Array_([
				new ArrayItem(new String_('bar'), new String_('foo')),
				new ArrayItem(new LNumber(2), new LNumber(1))
			])],
		];
	}

	public function testGetConstantValueExpressionForNonExistent() {
		$reflectedClass = new ReflectedClass(new ReflectionClass(ClassWithConstantsForReflectedTest::class));
		$this->assertNull($reflectedClass->getConstantValueExpression('NON_EXISTENT_CONST'));
	}
}