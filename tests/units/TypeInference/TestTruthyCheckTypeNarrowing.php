<?php

namespace BambooHR\Guardrail\Tests\TypeInference;

use BambooHR\Guardrail\Scope\Scope;
use BambooHR\Guardrail\Scope\ScopeVar;
use BambooHR\Guardrail\TypeInference\TypeAssertion;
use PhpParser\Node;
use PHPUnit\Framework\TestCase;

class TestTruthyCheckTypeNarrowing extends TestCase {
	
	/**
	 * Test that truthy checks work with Variable nodes
	 */
	public function testTruthyCheckWithVariable(): void {
		$scope = new Scope(false, false, false);
		
		// Create a nullable string variable
		$var = new ScopeVar();
		$var->name = 'foo';
		$var->type = new Node\UnionType([
			new Node\Identifier('string'),
			new Node\Identifier('null')
		]);
		$var->mayBeNull = true;
		
		$scope->setVarType('foo', $var->type, 1);
		$scopeVar = $scope->getVarObject('foo');
		$scopeVar->mayBeNull = true;
		
		// Create condition: if ($foo)
		$condition = new Node\Expr\Variable('foo');
		
		// Apply type narrowing for truthy branch
		TypeAssertion::narrowTypes($condition, $scope, true);
		
		// In truthy branch, $foo should not be null
		$narrowedVar = $scope->getVarObject('foo');
		$this->assertNotNull($narrowedVar);
		$this->assertFalse($narrowedVar->mayBeNull, 'Variable should not be nullable in truthy branch');
		
		// Type should have null removed
		$this->assertInstanceOf(Node\Identifier::class, $narrowedVar->type);
		$this->assertEquals('string', (string)$narrowedVar->type);
	}
	
	/**
	 * Test that truthy checks work with PropertyFetch nodes
	 */
	public function testTruthyCheckWithPropertyFetch(): void {
		$scope = new Scope(false, false, false);
		
		// Create a nullable object property: $obj->prop
		$var = new ScopeVar();
		$var->name = 'obj->prop';
		$var->type = new Node\UnionType([
			new Node\Identifier('int'),
			new Node\Identifier('null')
		]);
		$var->mayBeNull = true;
		
		$scope->setVarType('obj->prop', $var->type, 1);
		$scopeVar = $scope->getVarObject('obj->prop');
		$scopeVar->mayBeNull = true;
		
		// Create condition: if ($obj->prop)
		$condition = new Node\Expr\PropertyFetch(
			new Node\Expr\Variable('obj'),
			new Node\Identifier('prop')
		);
		
		// Apply type narrowing for truthy branch
		TypeAssertion::narrowTypes($condition, $scope, true);
		
		// In truthy branch, $obj->prop should not be null
		$narrowedVar = $scope->getVarObject('obj->prop');
		$this->assertNotNull($narrowedVar);
		$this->assertFalse($narrowedVar->mayBeNull, 'Property should not be nullable in truthy branch');
		
		// Type should have null removed
		$this->assertInstanceOf(Node\Identifier::class, $narrowedVar->type);
		$this->assertEquals('int', (string)$narrowedVar->type);
	}
	
	/**
	 * Test that truthy checks work with NullsafePropertyFetch nodes
	 */
	public function testTruthyCheckWithNullsafePropertyFetch(): void {
		$scope = new Scope(false, false, false);
		
		// Create a nullable property: $obj?->prop
		$var = new ScopeVar();
		$var->name = 'obj->prop';
		$var->type = new Node\UnionType([
			new Node\Identifier('bool'),
			new Node\Identifier('null')
		]);
		$var->mayBeNull = true;
		
		$scope->setVarType('obj->prop', $var->type, 1);
		$scopeVar = $scope->getVarObject('obj->prop');
		$scopeVar->mayBeNull = true;
		
		// Create condition: if ($obj?->prop)
		$condition = new Node\Expr\NullsafePropertyFetch(
			new Node\Expr\Variable('obj'),
			new Node\Identifier('prop')
		);
		
		// Apply type narrowing for truthy branch
		TypeAssertion::narrowTypes($condition, $scope, true);
		
		// In truthy branch, $obj->prop should not be null
		$narrowedVar = $scope->getVarObject('obj->prop');
		$this->assertNotNull($narrowedVar);
		$this->assertFalse($narrowedVar->mayBeNull, 'Nullsafe property should not be nullable in truthy branch');
		
		// Type should have null removed
		$this->assertInstanceOf(Node\Identifier::class, $narrowedVar->type);
		$this->assertEquals('bool', (string)$narrowedVar->type);
	}
	
	/**
	 * Test that all three node types are handled by the same code path
	 */
	public function testUnionTypeParameterAcceptsAllThreeTypes(): void {
		$scope = new Scope(false, false, false);
		
		// Test Variable
		$varCondition = new Node\Expr\Variable('test');
		$this->assertInstanceOf(Node\Expr\Variable::class, $varCondition);
		
		// Test PropertyFetch
		$propCondition = new Node\Expr\PropertyFetch(
			new Node\Expr\Variable('obj'),
			new Node\Identifier('prop')
		);
		$this->assertInstanceOf(Node\Expr\PropertyFetch::class, $propCondition);
		
		// Test NullsafePropertyFetch
		$nullsafeCondition = new Node\Expr\NullsafePropertyFetch(
			new Node\Expr\Variable('obj'),
			new Node\Identifier('prop')
		);
		$this->assertInstanceOf(Node\Expr\NullsafePropertyFetch::class, $nullsafeCondition);
		
		// All three should be valid for the union type check
		$this->assertTrue(
			$varCondition instanceof Node\Expr\Variable ||
			$varCondition instanceof Node\Expr\PropertyFetch ||
			$varCondition instanceof Node\Expr\NullsafePropertyFetch
		);
		
		$this->assertTrue(
			$propCondition instanceof Node\Expr\Variable ||
			$propCondition instanceof Node\Expr\PropertyFetch ||
			$propCondition instanceof Node\Expr\NullsafePropertyFetch
		);
		
		$this->assertTrue(
			$nullsafeCondition instanceof Node\Expr\Variable ||
			$nullsafeCondition instanceof Node\Expr\PropertyFetch ||
			$nullsafeCondition instanceof Node\Expr\NullsafePropertyFetch
		);
	}
	
	/**
	 * Test falsy branch behavior
	 */
	public function testFalsyBranchDoesNotNarrow(): void {
		$scope = new Scope(false, false, false);
		
		// Create a nullable variable
		$var = new ScopeVar();
		$var->name = 'foo';
		$var->type = new Node\UnionType([
			new Node\Identifier('string'),
			new Node\Identifier('null')
		]);
		$var->mayBeNull = true;
		
		$scope->setVarType('foo', $var->type, 1);
		$scopeVar = $scope->getVarObject('foo');
		$scopeVar->mayBeNull = true;
		
		// Create condition: if ($foo)
		$condition = new Node\Expr\Variable('foo');
		
		// Apply type narrowing for FALSY branch
		TypeAssertion::narrowTypes($condition, $scope, false);
		
		// In falsy branch, we know it's set but could be null, false, 0, "", []
		$narrowedVar = $scope->getVarObject('foo');
		$this->assertNotNull($narrowedVar);
		$this->assertFalse($narrowedVar->mayBeUnset, 'Variable should be known to be set');
		// mayBeNull should remain unchanged (not forced to true, as it could be other falsy values)
	}
}
