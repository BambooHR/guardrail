<?php

namespace BambooHR\Guardrail\Tests\TypeInference;

use BambooHR\Guardrail\Scope\Scope;
use BambooHR\Guardrail\TypeInference\TypeAssertion;
use PhpParser\Node;
use PHPUnit\Framework\TestCase;

class TestInstanceofUnionNarrowing extends TestCase {
	
	/**
	 * Test that instanceof checks combined with || narrow to union type
	 */
	public function testInstanceofOrChainNarrowsToUnion(): void {
		$scope = new Scope(false, false, false);
		
		// Set initial type as generic Node
		$scope->setVarType('condition', new Node\Name('PhpParser\Node'), 1);
		
		// Create condition: $condition instanceof Variable || $condition instanceof PropertyFetch || $condition instanceof NullsafePropertyFetch
		$condition = new Node\Expr\BinaryOp\BooleanOr(
			new Node\Expr\BinaryOp\BooleanOr(
				new Node\Expr\Instanceof_(
					new Node\Expr\Variable('condition'),
					new Node\Name\FullyQualified('PhpParser\Node\Expr\Variable')
				),
				new Node\Expr\Instanceof_(
					new Node\Expr\Variable('condition'),
					new Node\Name\FullyQualified('PhpParser\Node\Expr\PropertyFetch')
				)
			),
			new Node\Expr\Instanceof_(
				new Node\Expr\Variable('condition'),
				new Node\Name\FullyQualified('PhpParser\Node\Expr\NullsafePropertyFetch')
			)
		);
		
		// Apply type narrowing for truthy branch
		TypeAssertion::narrowTypes($condition, $scope, true);
		
		// In truthy branch, $condition should be narrowed to Variable|PropertyFetch|NullsafePropertyFetch
		$narrowedVar = $scope->getVarObject('condition');
		$this->assertNotNull($narrowedVar);
		$this->assertFalse($narrowedVar->mayBeNull, 'Variable should not be nullable after instanceof checks');
		$this->assertFalse($narrowedVar->mayBeUnset, 'Variable should be known to be set after instanceof checks');
		
		// Type should be a union type
		$this->assertInstanceOf(Node\UnionType::class, $narrowedVar->type);
		$this->assertCount(3, $narrowedVar->type->types, 'Should have 3 types in the union');
	}
	
	/**
	 * Test that two instanceof checks narrow to union of two types
	 */
	public function testTwoInstanceofChecksNarrowToUnion(): void {
		$scope = new Scope(false, false, false);
		
		// Set initial type
		$scope->setVarType('var', new Node\Name('mixed'), 1);
		
		// Create condition: $var instanceof Foo || $var instanceof Bar
		$condition = new Node\Expr\BinaryOp\BooleanOr(
			new Node\Expr\Instanceof_(
				new Node\Expr\Variable('var'),
				new Node\Name('Foo')
			),
			new Node\Expr\Instanceof_(
				new Node\Expr\Variable('var'),
				new Node\Name('Bar')
			)
		);
		
		// Apply type narrowing for truthy branch
		TypeAssertion::narrowTypes($condition, $scope, true);
		
		// In truthy branch, $var should be Foo|Bar
		$narrowedVar = $scope->getVarObject('var');
		$this->assertNotNull($narrowedVar);
		$this->assertInstanceOf(Node\UnionType::class, $narrowedVar->type);
		$this->assertCount(2, $narrowedVar->type->types);
	}
	
	/**
	 * Test that single instanceof in an OR doesn't create a union
	 */
	public function testSingleInstanceofDoesNotCreateUnion(): void {
		$scope = new Scope(false, false, false);
		
		// Set initial type
		$scope->setVarType('var', new Node\Name('mixed'), 1);
		
		// Create condition: $var instanceof Foo (single check, not in union)
		$condition = new Node\Expr\Instanceof_(
			new Node\Expr\Variable('var'),
			new Node\Name('Foo')
		);
		
		// Apply type narrowing for truthy branch
		TypeAssertion::narrowTypes($condition, $scope, true);
		
		// In truthy branch, $var should be just Foo (not a union)
		$narrowedVar = $scope->getVarObject('var');
		$this->assertNotNull($narrowedVar);
		$this->assertInstanceOf(Node\Name::class, $narrowedVar->type);
		$this->assertEquals('Foo', (string)$narrowedVar->type);
	}
	
	/**
	 * Test that instanceof checks on different variables don't narrow
	 */
	public function testInstanceofOnDifferentVariablesDoesNotNarrow(): void {
		$scope = new Scope(false, false, false);
		
		// Set initial types
		$scope->setVarType('var1', new Node\Name('mixed'), 1);
		$scope->setVarType('var2', new Node\Name('mixed'), 1);
		
		// Create condition: $var1 instanceof Foo || $var2 instanceof Bar (different variables)
		$condition = new Node\Expr\BinaryOp\BooleanOr(
			new Node\Expr\Instanceof_(
				new Node\Expr\Variable('var1'),
				new Node\Name('Foo')
			),
			new Node\Expr\Instanceof_(
				new Node\Expr\Variable('var2'),
				new Node\Name('Bar')
			)
		);
		
		// Apply type narrowing for truthy branch
		TypeAssertion::narrowTypes($condition, $scope, true);
		
		// Variables should not be narrowed because they're different
		$var1 = $scope->getVarObject('var1');
		$var2 = $scope->getVarObject('var2');
		
		// Types should remain as 'mixed' (not narrowed)
		$this->assertEquals('mixed', (string)$var1->type);
		$this->assertEquals('mixed', (string)$var2->type);
	}
}
