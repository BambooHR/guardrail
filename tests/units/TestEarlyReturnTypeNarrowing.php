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
 * Test type narrowing for early return patterns (guard clauses)
 */
class TestEarlyReturnTypeNarrowing extends TestCase {
	
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
	 * Create a scope with a nullable variable
	 */
	private function createScopeWithNullableVar(string $varName, string $className): Scope {
		$scope = new Scope(false, false, false, null);
		$type = new Node\Name($className);
		$scope->setVarType($varName, $type, 1);
		
		$var = $scope->getVarObject($varName);
		if ($var) {
			$var->mayBeNull = true;
		}
		
		return $scope;
	}

	public function testTruthyCheckNarrowsNullable() {
		// Test: if (!$obj) { return; }
		// After this, $obj should be non-null
		
		$scope = $this->createScopeWithNullableVar('obj', 'MyClass');
		
		// Before narrowing, obj is nullable
		$var = $scope->getVarObject('obj');
		$this->assertTrue($var->mayBeNull, "Variable should start as nullable");
		
		// Parse the condition: !$obj
		$condition = $this->parseExpression('!$obj');
		
		// Apply narrowing for the FALSY branch (what happens after the early return)
		// When !$obj is FALSE (i.e., $obj is truthy), we continue execution
		TypeAssertion::narrowTypes($condition, $scope, false);
		
		// After narrowing, obj should be non-null
		$var = $scope->getVarObject('obj');
		$this->assertFalse($var->mayBeNull, "Variable should be narrowed to non-null after truthy check");
	}

	public function testNullCheckNarrowsNullable() {
		// Test: if ($obj === null) { return; }
		// After this, $obj should be non-null
		
		$scope = $this->createScopeWithNullableVar('obj', 'MyClass');
		
		// Before narrowing, obj is nullable
		$var = $scope->getVarObject('obj');
		$this->assertTrue($var->mayBeNull, "Variable should start as nullable");
		
		// Parse the condition: $obj === null
		$condition = $this->parseExpression('$obj === null');
		
		// Apply narrowing for the FALSY branch (what happens after the early return)
		// When $obj === null is FALSE, we continue execution
		TypeAssertion::narrowTypes($condition, $scope, false);
		
		// After narrowing, obj should be non-null
		$var = $scope->getVarObject('obj');
		$this->assertFalse($var->mayBeNull, "Variable should be narrowed to non-null after null check");
	}

	public function testNotNullCheckNarrowsNullable() {
		// Test: if ($obj !== null) { /* use $obj */ }
		// Inside the if, $obj should be non-null
		
		$scope = $this->createScopeWithNullableVar('obj', 'MyClass');
		
		// Before narrowing, obj is nullable
		$var = $scope->getVarObject('obj');
		$this->assertTrue($var->mayBeNull, "Variable should start as nullable");
		
		// Parse the condition: $obj !== null
		$condition = $this->parseExpression('$obj !== null');
		
		// Apply narrowing for the TRUTHY branch
		TypeAssertion::narrowTypes($condition, $scope, true);
		
		// After narrowing, obj should be non-null
		$var = $scope->getVarObject('obj');
		$this->assertFalse($var->mayBeNull, "Variable should be narrowed to non-null in truthy branch");
	}

	public function testInstanceOfNarrowsNullable() {
		// Test: if (!($obj instanceof MyClass)) { return; }
		// After this, $obj should be non-null and of type MyClass
		
		$scope = $this->createScopeWithNullableVar('obj', 'MyClass');
		
		// Before narrowing, obj is nullable
		$var = $scope->getVarObject('obj');
		$this->assertTrue($var->mayBeNull, "Variable should start as nullable");
		
		// Parse the condition: $obj instanceof MyClass
		$condition = $this->parseExpression('$obj instanceof MyClass');
		
		// Apply narrowing for the TRUTHY branch
		TypeAssertion::narrowTypes($condition, $scope, true);
		
		// After narrowing, obj should be non-null
		$var = $scope->getVarObject('obj');
		$this->assertFalse($var->mayBeNull, "Variable should be narrowed to non-null after instanceof");
	}
}
