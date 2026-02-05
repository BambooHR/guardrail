<?php

namespace BambooHR\Guardrail\Tests\SymbolTable;

use BambooHR\Guardrail\SymbolTable\JsonSymbolTable;
use PhpParser\Node\Const_;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassConst;
use PHPUnit\Framework\TestCase;

class JsonSymbolTableTest extends TestCase {

	public function testSerializeConstPreservesArrayType() {
		$table = new JsonSymbolTable('/tmp/test.json', __DIR__);

		// Create a constant with an array value (no explicit type declaration)
		$arrayValue = new Array_([]);
		$const = new Const_('MY_ARRAY', $arrayValue);
		$classConst = new ClassConst([$const], 0);

		$serialized = $table->serializeConst($classConst);

		// Should contain type information
		$this->assertStringContainsString('CMY_ARRAY', $serialized);
		$this->assertStringContainsString(' ', $serialized); // Should have type index
	}

	public function testUnserializeConstRestoresArrayType() {
		$table = new JsonSymbolTable('/tmp/test.json', __DIR__);

		// Create and serialize an array constant
		$arrayValue = new Array_([]);
		$const = new Const_('MY_ARRAY', $arrayValue);
		$classConst = new ClassConst([$const], 0);
		$serialized = $table->serializeConst($classConst);

		// Unserialize it
		$unserialized = $table->unserializeConst($serialized);

		// The type should be preserved
		$this->assertNotNull($unserialized->type);
		$this->assertEquals('array', (string)$unserialized->type);
	}

	public function testSerializeConstPreservesIntType() {
		$table = new JsonSymbolTable('/tmp/test.json', __DIR__);

		$intValue = new LNumber(42);
		$const = new Const_('MY_INT', $intValue);
		$classConst = new ClassConst([$const], 0);

		$serialized = $table->serializeConst($classConst);
		$unserialized = $table->unserializeConst($serialized);

		$this->assertNotNull($unserialized->type);
		$this->assertEquals('int', (string)$unserialized->type);
	}

	public function testSerializeConstPreservesStringType() {
		$table = new JsonSymbolTable('/tmp/test.json', __DIR__);

		$stringValue = new String_('hello');
		$const = new Const_('MY_STRING', $stringValue);
		$classConst = new ClassConst([$const], 0);

		$serialized = $table->serializeConst($classConst);
		$unserialized = $table->unserializeConst($serialized);

		$this->assertNotNull($unserialized->type);
		$this->assertEquals('string', (string)$unserialized->type);
	}

	/**
	 * Test that constants without type info still work (backward compatibility)
	 */
	public function testUnserializeConstWithoutTypeInfo() {
		$table = new JsonSymbolTable('/tmp/test.json', __DIR__);

		// Old format without type information (name includes semicolon in regex capture)
		$serialized = 'CMY_CONST;';
		$unserialized = $table->unserializeConst($serialized);

		// The regex now correctly captures 'MY_CONST' without the semicolon
		$this->assertEquals('MY_CONST', $unserialized->consts[0]->name->toString());
		// Type should be null for old format
		$this->assertNull($unserialized->type);
	}
}
