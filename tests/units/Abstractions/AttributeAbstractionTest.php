<?php

namespace BambooHR\Guardrail\Tests\Abstractions;

use BambooHR\Guardrail\Abstractions\AttributeAbstraction;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPUnit\Framework\TestCase;

class AttributeAbstractionTest extends TestCase {
	public function testGetClassName() {
		$abstraction = new AttributeAbstraction(
			new Attribute(
				new Name('MyAttribute'),
				[]
			)
		);
		$this->assertEquals('MyAttribute', $abstraction->getClassName());
	}

	public function testGetArguments() {
		$args = [
			new Arg(new String_('value1')),
			new Arg(new String_('value2'), name: new Identifier('named'))
		];
		$attributeNode = new Attribute(
			new Name('MyAttribute'),
			[
				new Arg(new String_('value1')),
				new Arg(new String_('value2'), name: new Identifier('named'))
			]
		);
		$abstraction = new AttributeAbstraction($attributeNode);
		$this->assertEquals($args, $abstraction->getArguments());
	}

	public function testGetArgumentsEmpty() {
		$attributeNode = new Attribute(
			new Name('MyAttribute'),
			[]
		);
		$abstraction = new AttributeAbstraction($attributeNode);
		$this->assertEmpty($abstraction->getArguments());
	}
}
