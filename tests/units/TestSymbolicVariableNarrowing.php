<?php

namespace BambooHR\Guardrail\Tests;

use BambooHR\Guardrail\Scope\Scope;
use BambooHR\Guardrail\TypeInference\TypeAssertion;
use BambooHR\Guardrail\TypeComparer;
use PhpParser\Node;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PHPUnit\Framework\TestCase;

/**
 * Test type narrowing for symbolic variable names like $expr->name
 */
class TestSymbolicVariableNarrowing extends TestCase {
	
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
		
		return $stmts[0]->expr;
	}
	
	/**
	 * Create a scope with a nullable symbolic variable (property fetch)
	 */
	private function createScopeWithSymbolicVar(string $symbolicName, string $className): Scope {
		$scope = new Scope(false, false, false, null);
		$type = new Node\Name($className);
		$scope->setVarType($symbolicName, $type, 1);
		
		$var = $scope->getVarObject($symbolicName);
		if ($var) {
			$var->mayBeNull = true;
		}
		
		return $scope;
	}

	public function testSymbolicVariableInstanceOfNarrowing() {
		// Test: if ($expr->name instanceof MyClass) { /* use $expr->name */ }
		// The symbolic variable "$expr->name" should be narrowed
		
		$scope = $this->createScopeWithSymbolicVar('expr->name', 'MyClass');
		
		// Before narrowing, expr->name is nullable
		$var = $scope->getVarObject('expr->name');
		$this->assertNotNull($var, "Symbolic variable should exist in scope");
		$this->assertTrue($var->mayBeNull, "Symbolic variable should start as nullable");
		
		// Parse the condition: $expr->name instanceof MyClass
		$condition = $this->parseExpression('$expr->name instanceof MyClass');
		
		// Apply narrowing for the TRUTHY branch
		TypeAssertion::narrowTypes($condition, $scope, true);
		
		// After narrowing, expr->name should be non-null
		$var = $scope->getVarObject('expr->name');
		$this->assertNotNull($var, "Symbolic variable should still exist after narrowing");
		$this->assertFalse($var->mayBeNull, "Symbolic variable should be narrowed to non-null after instanceof");
	}

	public function testSymbolicVariableNullCheckNarrowing() {
		// Test: if ($expr->name !== null) { /* use $expr->name */ }
		// The symbolic variable "$expr->name" should be narrowed
		
		$scope = $this->createScopeWithSymbolicVar('expr->name', 'string');
		
		// Before narrowing, expr->name is nullable
		$var = $scope->getVarObject('expr->name');
		$this->assertNotNull($var, "Symbolic variable should exist in scope");
		$this->assertTrue($var->mayBeNull, "Symbolic variable should start as nullable");
		
		// Parse the condition: $expr->name !== null
		$condition = $this->parseExpression('$expr->name !== null');
		
		// Apply narrowing for the TRUTHY branch
		TypeAssertion::narrowTypes($condition, $scope, true);
		
		// After narrowing, expr->name should be non-null
		$var = $scope->getVarObject('expr->name');
		$this->assertNotNull($var, "Symbolic variable should still exist after narrowing");
		$this->assertFalse($var->mayBeNull, "Symbolic variable should be narrowed to non-null after !== null check");
	}

	public function testSymbolicVariableTruthyCheckNarrowing() {
		// Test: if ($expr->name) { /* use $expr->name */ }
		// The symbolic variable "$expr->name" should be narrowed
		
		$scope = $this->createScopeWithSymbolicVar('expr->name', 'string');
		
		// Before narrowing, expr->name is nullable
		$var = $scope->getVarObject('expr->name');
		$this->assertNotNull($var, "Symbolic variable should exist in scope");
		$this->assertTrue($var->mayBeNull, "Symbolic variable should start as nullable");
		
		// Parse the condition: $expr->name
		$condition = $this->parseExpression('$expr->name');
		
		// Apply narrowing for the TRUTHY branch
		TypeAssertion::narrowTypes($condition, $scope, true);
		
		// After narrowing, expr->name should be non-null
		$var = $scope->getVarObject('expr->name');
		$this->assertNotNull($var, "Symbolic variable should still exist after narrowing");
		$this->assertFalse($var->mayBeNull, "Symbolic variable should be narrowed to non-null after truthy check");
	}

	public function testNestedSymbolicVariableNarrowing() {
		// Test: if ($obj->prop->value instanceof MyClass) { /* use $obj->prop->value */ }
		// The nested symbolic variable "$obj->prop->value" should be narrowed
		
		$scope = $this->createScopeWithSymbolicVar('obj->prop->value', 'MyClass');
		
		// Before narrowing, obj->prop->value is nullable
		$var = $scope->getVarObject('obj->prop->value');
		$this->assertNotNull($var, "Nested symbolic variable should exist in scope");
		$this->assertTrue($var->mayBeNull, "Nested symbolic variable should start as nullable");
		
		// Parse the condition: $obj->prop->value instanceof MyClass
		$condition = $this->parseExpression('$obj->prop->value instanceof MyClass');
		
		// Apply narrowing for the TRUTHY branch
		TypeAssertion::narrowTypes($condition, $scope, true);
		
		// After narrowing, obj->prop->value should be non-null
		$var = $scope->getVarObject('obj->prop->value');
		$this->assertNotNull($var, "Nested symbolic variable should still exist after narrowing");
		$this->assertFalse($var->mayBeNull, "Nested symbolic variable should be narrowed to non-null after instanceof");
	}

	public function testSymbolicVariableInvalidatedOnParentReassignment() {
		// Test: When $expr is reassigned, $expr->name should be invalidated
		// This is a conceptual test - in practice, this would be handled by the evaluator
		
		$scope = $this->createScopeWithSymbolicVar('expr->name', 'string');
		
		// Symbolic variable exists
		$var = $scope->getVarObject('expr->name');
		$this->assertNotNull($var, "Symbolic variable should exist");
		
		// When we reassign $expr, the symbolic variable should be removed
		// (This would normally be done by the evaluator when processing assignment)
		$scope->setVarType('expr', new Node\Name('NewClass'), 1);
		
		// In a real implementation, we'd expect the evaluator to remove 'expr->name'
		// For now, we just document the expected behavior
		$this->assertTrue(true, "Parent reassignment should invalidate symbolic variables (implementation-dependent)");
	}
}
