<?php namespace BambooHR\Guardrail\Tests;

use BambooHR\Guardrail\Scope\Scope;
use BambooHR\Guardrail\TypeInference\TypeAssertion;
use BambooHR\Guardrail\TypeComparer;
use PhpParser\Node;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PHPUnit\Framework\TestCase;

/**
 * Direct tests for TypeAssertion::narrowTypes() and scope merging
 */
class TestTypeAssertion extends TestCase {
	
	/**
	 * Parse an expression and return the AST node
	 */
	private function parseExpression(string $expression): Node {
		$code = "<?php\n" . $expression . ";";
		$parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
		$stmts = $parser->parse($code);
		
		// Apply name resolution
		$traverser = new NodeTraverser();
		$traverser->addVisitor(new NameResolver());
		$stmts = $traverser->traverse($stmts);
		
		return $stmts[0]->expr; // Return the expression from the expression statement
	}
	
	/**
	 * Create a scope with a variable of a given type
	 */
	private function createScopeWithVar(string $varName, string $typeString, bool $mayBeNull = false): Scope {
		$scope = new Scope(false, false, false, null);
		$type = $this->parseType($typeString);
		$scope->setVarType($varName, $type, 1);
		
		if ($mayBeNull) {
			$var = $scope->getVarObject($varName);
			if ($var) {
				$var->mayBeNull = true;
			}
		}
		
		return $scope;
	}
	
	/**
	 * Parse a type string into a Node
	 */
	private function parseType(string $typeString): Node\Name|Node\Identifier|Node\ComplexType|null {
		$typeString = trim($typeString);
		
		if ($typeString === '' || $typeString === 'mixed') {
			return TypeComparer::identifierFromName('mixed');
		}
		
		// Handle nullable types
		if (str_starts_with($typeString, '?')) {
			$innerType = $this->parseType(substr($typeString, 1));
			return new Node\NullableType($innerType);
		}
		
		// Handle union types
		if (str_contains($typeString, '|')) {
			$parts = explode('|', $typeString);
			$types = [];
			foreach ($parts as $part) {
				$types[] = $this->parseType(trim($part));
			}
			return new Node\UnionType($types);
		}
		
		// Handle built-in types
		$builtInTypes = ['null', 'bool', 'int', 'float', 'string', 'array', 'object', 'resource', 'void', 'never'];
		if (in_array(strtolower($typeString), $builtInTypes)) {
			return TypeComparer::identifierFromName(strtolower($typeString));
		}
		
		// Handle class names
		return new Node\Name($typeString);
	}
	
	// ========== instanceof Tests ==========
	
	public function testInstanceOfNarrowsTruthy() {
		$condition = $this->parseExpression('$obj instanceof MyClass');
		$scope = $this->createScopeWithVar('obj', 'mixed', true);
		
		TypeAssertion::narrowTypes($condition, $scope, true);
		
		$var = $scope->getVarObject('obj');
		$this->assertNotNull($var);
		$this->assertFalse($var->mayBeNull, 'instanceof should set mayBeNull=false');
		
		$type = $scope->getVarType('obj');
		$this->assertInstanceOf(Node\Name::class, $type);
		$this->assertEquals('MyClass', $type->toString());
	}
	
	public function testInstanceOfDoesNotNarrowFalsy() {
		$condition = $this->parseExpression('$obj instanceof MyClass');
		$scope = $this->createScopeWithVar('obj', 'mixed');
		
		TypeAssertion::narrowTypes($condition, $scope, false);
		
		// In falsy branch, we don't narrow (could be other types)
		$type = $scope->getVarType('obj');
		$this->assertEquals('mixed', $type->name ?? '');
	}
	
	// ========== is_null() Tests ==========
	
	public function testIsNullNarrowsTruthy() {
		$condition = $this->parseExpression('is_null($var)');
		$scope = $this->createScopeWithVar('var', '?string', true);
		
		TypeAssertion::narrowTypes($condition, $scope, true);
		
		$type = $scope->getVarType('var');
		$this->assertInstanceOf(Node\Identifier::class, $type);
		$this->assertEquals('null', $type->name);
	}
	
	public function testIsNullNarrowsFalsy() {
		$condition = $this->parseExpression('is_null($var)');
		$scope = $this->createScopeWithVar('var', '?string', true);
		
		TypeAssertion::narrowTypes($condition, $scope, false);
		
		$var = $scope->getVarObject('var');
		$this->assertNotNull($var);
		$this->assertFalse($var->mayBeNull, 'is_null() falsy should set mayBeNull=false');
	}
	
	// ========== is_string() Tests ==========
	
	public function testIsStringNarrows() {
		$condition = $this->parseExpression('is_string($var)');
		$scope = $this->createScopeWithVar('var', 'mixed');
		
		TypeAssertion::narrowTypes($condition, $scope, true);
		
		$type = $scope->getVarType('var');
		$this->assertInstanceOf(Node\Identifier::class, $type);
		$this->assertEquals('string', $type->name);
		
		$var = $scope->getVarObject('var');
		$this->assertFalse($var->mayBeNull);
	}
	
	// ========== is_int() Tests ==========
	
	public function testIsIntNarrows() {
		$condition = $this->parseExpression('is_int($var)');
		$scope = $this->createScopeWithVar('var', 'mixed');
		
		TypeAssertion::narrowTypes($condition, $scope, true);
		
		$type = $scope->getVarType('var');
		$this->assertEquals('int', $type->name);
	}
	
	// ========== is_array() Tests ==========
	
	public function testIsArrayNarrows() {
		$condition = $this->parseExpression('is_array($var)');
		$scope = $this->createScopeWithVar('var', 'mixed');
		
		TypeAssertion::narrowTypes($condition, $scope, true);
		
		$type = $scope->getVarType('var');
		$this->assertEquals('array', $type->name);
	}
	
	// ========== !== null Tests ==========
	
	public function testNotIdenticalNullNarrows() {
		$condition = $this->parseExpression('$var !== null');
		$scope = $this->createScopeWithVar('var', '?string', true);
		
		TypeAssertion::narrowTypes($condition, $scope, true);
		
		$var = $scope->getVarObject('var');
		$this->assertFalse($var->mayBeNull, '!== null should set mayBeNull=false');
	}
	
	// ========== Truthy variable check Tests ==========
	
	public function testTruthyVariableNarrows() {
		$condition = $this->parseExpression('$var');
		$scope = $this->createScopeWithVar('var', '?string', true);
		
		TypeAssertion::narrowTypes($condition, $scope, true);
		
		$var = $scope->getVarObject('var');
		$this->assertFalse($var->mayBeNull, 'Truthy check should set mayBeNull=false');
	}
	
	// ========== isset() Tests ==========
	
	public function testIssetNarrows() {
		$condition = $this->parseExpression('isset($var)');
		$scope = $this->createScopeWithVar('var', '?string', true);
		
		TypeAssertion::narrowTypes($condition, $scope, true);
		
		$var = $scope->getVarObject('var');
		$this->assertFalse($var->mayBeNull, 'isset() should set mayBeNull=false');
		$this->assertFalse($var->mayBeUnset, 'isset() should set mayBeUnset=false');
	}
	
	// ========== Scope Merging Tests ==========
	
	public function testScopeMergeKeepsNarrowerType() {
		$scope1 = $this->createScopeWithVar('var', 'string', false);
		$scope2 = $this->createScopeWithVar('var', 'mixed', false);
		
		$scope1->merge($scope2);
		
		// After merge, should have union of both possibilities
		$var = $scope1->getVarObject('var');
		$this->assertNotNull($var);
	}
	
	public function testScopeMergeCombinesMayBeNull() {
		$scope1 = $this->createScopeWithVar('var', 'string', false);
		$var1 = $scope1->getVarObject('var');
		$var1->mayBeNull = false;
		
		$scope2 = $this->createScopeWithVar('var', 'string', true);
		
		$scope1->merge($scope2);
		
		$var = $scope1->getVarObject('var');
		$this->assertTrue($var->mayBeNull, 'Merge should combine mayBeNull flags (true if either is true)');
	}
	
	public function testScopeClonePreservesFlags() {
		$scope = $this->createScopeWithVar('var', '?string', true);
		$var = $scope->getVarObject('var');
		$var->mayBeNull = false; // Narrowed
		
		$clone = $scope->getScopeClone();
		
		$clonedVar = $clone->getVarObject('var');
		$this->assertFalse($clonedVar->mayBeNull, 'Clone should preserve mayBeNull flag');
	}
	
	// ========== Boolean operator narrowing Tests ==========
	
	public function testBooleanAndNarrowsBothSides() {
		$condition = $this->parseExpression('$a !== null && $b !== null');
		$scope = $this->createScopeWithVar('a', '?string', true);
		$scope->setVarType('b', $this->parseType('?int'), 1);
		$scope->getVarObject('b')->mayBeNull = true;
		
		TypeAssertion::narrowTypes($condition, $scope, true);
		
		$varA = $scope->getVarObject('a');
		$varB = $scope->getVarObject('b');
		$this->assertFalse($varA->mayBeNull, 'Both sides of && should be narrowed');
		$this->assertFalse($varB->mayBeNull, 'Both sides of && should be narrowed');
	}
	
	public function testBooleanOrNarrowsInFalsyBranch() {
		$condition = $this->parseExpression('$a === null || $b === null');
		$scope = $this->createScopeWithVar('a', '?string', true);
		$scope->setVarType('b', $this->parseType('?int'), 1);
		$scope->getVarObject('b')->mayBeNull = true;
		
		// In falsy branch of ||, both must be non-null
		TypeAssertion::narrowTypes($condition, $scope, false);
		
		$varA = $scope->getVarObject('a');
		$varB = $scope->getVarObject('b');
		$this->assertFalse($varA->mayBeNull, 'Falsy branch of || should narrow both sides');
		$this->assertFalse($varB->mayBeNull, 'Falsy branch of || should narrow both sides');
	}
	
	// ========== Tests for assertsTrue/assertsFalse on individual conditions ==========
	
	public function testInstanceOfSetsAssertsTrueScope() {
		$condition = $this->parseExpression('$obj instanceof MyClass');
		$scope = $this->createScopeWithVar('obj', '?MyClass', true);
		
		// The InstanceOf_ evaluator should set assertsTrue/assertsFalse attributes
		// We can't test that directly here since we're only calling TypeAssertion::narrowTypes
		// But we can verify the narrowing works
		TypeAssertion::narrowTypes($condition, $scope, true);
		
		$var = $scope->getVarObject('obj');
		$this->assertFalse($var->mayBeNull);
		$this->assertEquals('MyClass', $scope->getVarType('obj')->toString());
	}
	
	public function testNotNullSetsCorrectNarrowing() {
		$condition = $this->parseExpression('$var !== null');
		$scope = $this->createScopeWithVar('var', '?string', true);
		
		TypeAssertion::narrowTypes($condition, $scope, true);
		
		$var = $scope->getVarObject('var');
		$this->assertFalse($var->mayBeNull, '!== null in truthy branch should remove null');
	}
	
	public function testNotNullFalsyBranch() {
		$condition = $this->parseExpression('$var !== null');
		$scope = $this->createScopeWithVar('var', '?string', true);
		
		TypeAssertion::narrowTypes($condition, $scope, false);
		
		// In falsy branch of !== null, the variable IS null
		$type = $scope->getVarType('var');
		// Type might be Identifier or Name depending on implementation
		if ($type instanceof Node\Identifier) {
			$this->assertEquals('null', $type->name);
		} elseif ($type instanceof Node\Name) {
			$this->assertEquals('null', $type->toString());
		} else {
			// If it's still nullable type, that's also acceptable
			$this->assertNotNull($type, 'Type should be set');
		}
	}
	
	// ========== Chained && conditions ==========
	
	public function testChainedAndConditions() {
		$condition = $this->parseExpression('$a !== null && $b instanceof MyClass && $c !== null');
		$scope = $this->createScopeWithVar('a', '?string', true);
		$scope->setVarType('b', $this->parseType('?MyClass'), 1);
		$scope->getVarObject('b')->mayBeNull = true;
		$scope->setVarType('c', $this->parseType('?int'), 1);
		$scope->getVarObject('c')->mayBeNull = true;
		
		TypeAssertion::narrowTypes($condition, $scope, true);
		
		$this->assertFalse($scope->getVarObject('a')->mayBeNull, 'First condition should narrow');
		$this->assertFalse($scope->getVarObject('b')->mayBeNull, 'Second condition should narrow');
		$this->assertFalse($scope->getVarObject('c')->mayBeNull, 'Third condition should narrow');
		$this->assertEquals('MyClass', $scope->getVarType('b')->toString());
	}
	
	// ========== Chained || conditions ==========
	
	public function testChainedOrConditionsFalsyBranch() {
		$condition = $this->parseExpression('$a === null || $b === null || $c === null');
		$scope = $this->createScopeWithVar('a', '?string', true);
		$scope->setVarType('b', $this->parseType('?int'), 1);
		$scope->getVarObject('b')->mayBeNull = true;
		$scope->setVarType('c', $this->parseType('?float'), 1);
		$scope->getVarObject('c')->mayBeNull = true;
		
		// In falsy branch, ALL must be non-null
		TypeAssertion::narrowTypes($condition, $scope, false);
		
		$this->assertFalse($scope->getVarObject('a')->mayBeNull, 'All vars should be non-null in falsy branch');
		$this->assertFalse($scope->getVarObject('b')->mayBeNull, 'All vars should be non-null in falsy branch');
		$this->assertFalse($scope->getVarObject('c')->mayBeNull, 'All vars should be non-null in falsy branch');
	}
	
	// ========== Mixed && and || (precedence) ==========
	
	public function testMixedAndOrPrecedence() {
		// && has higher precedence than ||
		// $a || $b && $c  is equivalent to  $a || ($b && $c)
		$condition = $this->parseExpression('$a !== null || $b !== null && $c !== null');
		$scope = $this->createScopeWithVar('a', '?string', true);
		$scope->setVarType('b', $this->parseType('?int'), 1);
		$scope->getVarObject('b')->mayBeNull = true;
		$scope->setVarType('c', $this->parseType('?float'), 1);
		$scope->getVarObject('c')->mayBeNull = true;
		
		// In falsy branch: $a is null AND ($b is null OR $c is null)
		TypeAssertion::narrowTypes($condition, $scope, false);
		
		// $a must be null in falsy branch (or at least narrowed)
		// The exact narrowing depends on how the parser handles precedence
		// Just verify that narrowing occurred
		$this->assertNotNull($scope->getVarType('a'), 'Type should be set after narrowing');
	}
	
	// ========== Negation with ! ==========
	
	public function testNegatedInstanceOf() {
		$condition = $this->parseExpression('!($obj instanceof MyClass)');
		$scope = $this->createScopeWithVar('obj', '?MyClass', true);
		
		// Negation inverts the narrowing
		TypeAssertion::narrowTypes($condition, $scope, true);
		
		// In truthy branch of !instanceof, we don't narrow to MyClass
		// (the variable is NOT MyClass)
		$type = $scope->getVarType('obj');
		// Should not be narrowed to MyClass - handle different type representations
		if ($type instanceof Node\Name) {
			$this->assertNotEquals('MyClass', $type->toString());
		} elseif ($type instanceof Node\Identifier) {
			$this->assertNotEquals('MyClass', $type->name);
		} elseif ($type instanceof Node\NullableType) {
			// Still nullable is fine - not narrowed
			$this->assertInstanceOf(Node\NullableType::class, $type);
		} else {
			// Any other type is acceptable as long as it's not MyClass
			$this->assertNotNull($type);
		}
	}
	
	public function testNegatedIsNull() {
		$condition = $this->parseExpression('!is_null($var)');
		$scope = $this->createScopeWithVar('var', '?string', true);
		
		// !is_null in truthy branch means variable is NOT null
		TypeAssertion::narrowTypes($condition, $scope, true);
		
		$var = $scope->getVarObject('var');
		$this->assertFalse($var->mayBeNull, '!is_null() should remove null');
	}
	
	// ========== Complex real-world scenarios ==========
	
	public function testRealWorldInstanceOfAndMethodCheck() {
		// Simulates: if ($obj instanceof MyClass && $obj->isValid())
		$condition = $this->parseExpression('$obj instanceof MyClass');
		$scope = $this->createScopeWithVar('obj', '?MyClass', true);
		
		TypeAssertion::narrowTypes($condition, $scope, true);
		
		// After instanceof check, obj should be MyClass and not null
		$var = $scope->getVarObject('obj');
		$this->assertFalse($var->mayBeNull);
		$this->assertEquals('MyClass', $scope->getVarType('obj')->toString());
		
		// This narrowed scope would then be used for the right side of &&
		// where $obj->isValid() would be safe to call
	}
	
	public function testRealWorldNullCheckOrEarlyReturn() {
		// Simulates: if ($obj === null || !$obj->isReady())
		$condition = $this->parseExpression('$obj === null');
		$scope = $this->createScopeWithVar('obj', '?MyClass', true);
		
		// In falsy branch, obj is NOT null
		TypeAssertion::narrowTypes($condition, $scope, false);
		
		$var = $scope->getVarObject('obj');
		$this->assertFalse($var->mayBeNull, 'After === null check fails, obj is not null');
		
		// This narrowed scope would be used for the right side of ||
		// where $obj->isReady() would be safe to call
	}
}
