<?php namespace BambooHR\Guardrail\Tests;

use BambooHR\Guardrail\Exceptions\DocBlockParserException;
use BambooHR\Guardrail\TypeParser;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\UnionType;
use PHPUnit\Framework\TestCase;

class TypeParserTest extends TestCase {

	function setUp(): void {
		// This sets up some static variables we need.
		new TestConfig("TestParserTest", "Standard.*");
	}

	function testSimpleType() {
		$parser = new TypeParser(fn($foo) => $foo);
		$parsed = $parser->parse("Foo");
		$this->assertInstanceOf(Name::class, $parsed);
	}

	function testUnionType() {
		$parser = new TypeParser(fn($foo) => $foo);
		$parsed = $parser->parse("Fiz|Buz|Biz");
		$this->assertInstanceOf(UnionType::class, $parsed);
	}


	function testIntersectionType() {
		$parser = new TypeParser(fn($foo) => $foo);
		$parsed = $parser->parse("(Foo&Bar)");
		$this->assertInstanceOf(IntersectionType::class, $parsed);

		$parsed = $parser->parse("(Foo&Bar&Baz)");
		$this->assertInstanceOf(IntersectionType::class, $parsed);

		try {
			$parser->parse("(Foo)");
			$this->fail("Should have thrown");
		} catch (DocBlockParserException) {
			$this->addToAssertionCount(1);
		}

		try {
			$parser->parse("(");
			$this->fail("Should have thrown");
		} catch (DocBlockParserException) {
			$this->addToAssertionCount(1);
		}

		try {
			$parser->parse(")");
			$this->fail("Should have thrown");
		} catch (DocBlockParserException) {
			$this->addToAssertionCount(1);
		}
	}

	function testArrayType() {
		$parser = new TypeParser(fn($foo) => $foo);
		$parsed = $parser->parse("string[]");
		$this->assertInstanceOf(Identifier::class, $parsed);
		$this->assertEquals("array", strval($parsed));
		$this->assertInstanceOf( Identifier::class, $parsed->getAttribute("templates")[0]);
	}

	function testNestedArrayType() {
		$parser = new TypeParser(fn($foo) => $foo);
		$parsed = $parser->parse("int[][]");
		$this->assertInstanceOf(Identifier::class, $parsed);
		$this->assertEquals("array", strval($parsed));

		$this->assertInstanceOf( Identifier::class, $parsed->getAttribute("templates")[0]);
		$this->assertEquals("array", strval($parsed->getAttribute('templates')[0]));

		$this->assertInstanceOf( Identifier::class, $parsed->getAttribute('templates')[0]->getAttribute('templates')[0]);
		$this->assertEquals( "int", $parsed->getAttribute('templates')[0]->getAttribute('templates')[0]);

	}

	function testCollectionTemplate() {
		$parser = new TypeParser(fn($foo) => $foo);
		$parsed = $parser->parse("Stack<int>");
		$this->assertInstanceOf(Name::class, $parsed);
		$this->assertEquals("Stack", strval($parsed));
		$this->assertInstanceOf( Identifier::class, $parsed->getAttribute("templates")[0]);
		$this->assertEquals('int', strval($parsed->getAttribute('templates')[0]));
	}
}