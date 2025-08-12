<?php

namespace BambooHR\Guardrail\Tests\Abstractions;

use BambooHR\Guardrail\Abstractions\ReflectedAttribute;
use PhpParser\Node\Arg;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Attribute;

#[Attribute]
class TestAttributeForReflectedAttributeTest {
    public function __construct(public string $value, public int $number = 42) {}
}

#[TestAttributeForReflectedAttributeTest("hello", number: 99)]
class ClassWithReflectedAttribute {}

#[TestAttributeForReflectedAttributeTest("world")]
class ClassWithReflectedAttributeDefault {}


class ReflectedAttributeTest extends TestCase {
    public function testGetClassName() {
        $reflectionClass = new ReflectionClass(ClassWithReflectedAttribute::class);
        $reflectionAttribute = $reflectionClass->getAttributes()[0];
        $reflectedAttribute = new ReflectedAttribute($reflectionAttribute);

        $this->assertEquals(TestAttributeForReflectedAttributeTest::class, $reflectedAttribute->getClassName());
    }

    public function testGetArguments() {
        $reflectionClass = new ReflectionClass(ClassWithReflectedAttribute::class);
        $reflectionAttribute = $reflectionClass->getAttributes()[0];
        $reflectedAttribute = new ReflectedAttribute($reflectionAttribute);

        $expectedArgs = [
            new Arg(new String_('hello')),
            new Arg(new LNumber(99), name: new Identifier('number'))
        ];

        $this->assertEquals($expectedArgs, $reflectedAttribute->getArguments());
    }

    public function testGetArgumentsDoesNotReturnDefaultedParameters() {
        $reflectionClass = new ReflectionClass(ClassWithReflectedAttributeDefault::class);
        $reflectionAttribute = $reflectionClass->getAttributes()[0];
        $reflectedAttribute = new ReflectedAttribute($reflectionAttribute);

        $expectedArgs = [
            new Arg(new String_('world'))
        ];

        $this->assertEquals($expectedArgs, $reflectedAttribute->getArguments());
    }
}
