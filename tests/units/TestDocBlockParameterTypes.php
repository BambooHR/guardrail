<?php

namespace BambooHR\Guardrail\Tests;

use BambooHR\Guardrail\Config;
use BambooHR\Guardrail\Evaluators\FunctionLike;
use BambooHR\Guardrail\Metrics\MetricOutputInterface;
use BambooHR\Guardrail\NodeVisitors\DocBlockNameResolver;
use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\InMemorySymbolTable;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

/**
 * Test that DocBlock parameter types are properly inferred for null checking
 */
class TestDocBlockParameterTypes extends TestCase {
	
	private InMemorySymbolTable $symbolTable;
	private ScopeStack $scopeStack;
	private FunctionLike $functionEvaluator;
	
	protected function setUp(): void {
		// Enable DocBlock parameter types using reflection
		$reflection = new \ReflectionClass(Config::class);
		$property = $reflection->getProperty('useDocBlockForParameters');
		$property->setAccessible(true);
		$property->setValue(null, true);
		
		$this->symbolTable = new InMemorySymbolTable('test');
		
		$output = $this->createMock(OutputInterface::class);
		$metricOutput = $this->createMock(MetricOutputInterface::class);
		$config = $this->createMock(Config::class);
		
		$this->scopeStack = new ScopeStack($output, $metricOutput, $config);
		$this->scopeStack->setCurrentFile('test.php');
		
		$this->functionEvaluator = new FunctionLike();
	}
	
	protected function tearDown(): void {
		// Reset the static property
		$reflection = new \ReflectionClass(Config::class);
		$property = $reflection->getProperty('useDocBlockForParameters');
		$property->setAccessible(true);
		$property->setValue(null, false);
	}
	
	/**
	 * Parse PHP code and apply DocBlockNameResolver
	 */
	private function parseCodeWithDocBlocks(string $code): array {
		$parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
		$stmts = $parser->parse($code);
		
		// Apply name resolution and docblock processing
		$traverser = new NodeTraverser();
		$nameResolver = new NameResolver();
		$traverser->addVisitor($nameResolver);
		$traverser->addVisitor(new DocBlockNameResolver($nameResolver->getNameContext()));
		$stmts = $traverser->traverse($stmts);
		
		return $stmts;
	}
	
	/**
	 * Test that a simple docblock type parameter is recognized as non-null
	 */
	public function testSimpleDocBlockTypeIsNonNull(): void {
		$code = '<?php
		class TestClass {
			public function method(): void {}
		}
		
		/**
		 * @param TestClass $obj
		 */
		function testFunc($obj) {
			// $obj should be non-null
		}
		';
		
		$stmts = $this->parseCodeWithDocBlocks($code);
		
		// Find the function
		$function = null;
		foreach ($stmts as $stmt) {
			if ($stmt instanceof \PhpParser\Node\Stmt\Function_ && $stmt->name->name === 'testFunc') {
				$function = $stmt;
				break;
			}
		}
		
		$this->assertNotNull($function, 'Function testFunc should be found');
		
		// Push global scope
		$this->scopeStack->pushScope(new \BambooHR\Guardrail\Scope\Scope(true, true, false));
		
		// Enter the function - this should set up parameter types
		$this->functionEvaluator->onEnter($function, $this->symbolTable, $this->scopeStack);
		
		// Check that $obj has a type set
		$objType = $this->scopeStack->getVarType('obj');
		$this->assertNotNull($objType, 'Parameter $obj should have a type from docblock');
		
		// Check that $obj is not marked as nullable
		$objVar = $this->scopeStack->getVarObject('obj');
		$this->assertNotNull($objVar, 'Parameter $obj should exist in scope');
		$this->assertFalse($objVar->mayBeNull, 'Parameter $obj should not be nullable (no null in docblock)');
	}
	
	/**
	 * Test that a union type parameter is recognized as non-null
	 */
	public function testUnionTypeDocBlockIsNonNull(): void {
		$code = '<?php
		class TestClass1 {
			public function method(): void {}
		}
		
		class TestClass2 {
			public function method(): void {}
		}
		
		/**
		 * @param TestClass1|TestClass2 $obj
		 */
		function testFunc($obj) {
			// $obj should be non-null
		}
		';
		
		$stmts = $this->parseCodeWithDocBlocks($code);
		
		// Find the function
		$function = null;
		foreach ($stmts as $stmt) {
			if ($stmt instanceof \PhpParser\Node\Stmt\Function_ && $stmt->name->name === 'testFunc') {
				$function = $stmt;
				break;
			}
		}
		
		$this->assertNotNull($function, 'Function testFunc should be found');
		
		// Push global scope
		$this->scopeStack->pushScope(new \BambooHR\Guardrail\Scope\Scope(true, true, false));
		
		// Enter the function
		$this->functionEvaluator->onEnter($function, $this->symbolTable, $this->scopeStack);
		
		// Check that $obj has a union type
		$objType = $this->scopeStack->getVarType('obj');
		$this->assertNotNull($objType, 'Parameter $obj should have a type from docblock');
		$this->assertInstanceOf(\PhpParser\Node\UnionType::class, $objType, 'Parameter $obj should have a UnionType');
		
		// Check that $obj is not marked as nullable
		$objVar = $this->scopeStack->getVarObject('obj');
		$this->assertNotNull($objVar, 'Parameter $obj should exist in scope');
		$this->assertFalse($objVar->mayBeNull, 'Parameter $obj should not be nullable (no null in union)');
	}
	
	/**
	 * Test that a nullable union type parameter is recognized as nullable
	 */
	public function testNullableUnionTypeDocBlockIsNullable(): void {
		$code = '<?php
		class TestClass1 {
			public function method(): void {}
		}
		
		class TestClass2 {
			public function method(): void {}
		}
		
		/**
		 * @param TestClass1|TestClass2|null $obj
		 */
		function testFunc($obj) {
			// $obj should be nullable
		}
		';
		
		$stmts = $this->parseCodeWithDocBlocks($code);
		
		// Find the function
		$function = null;
		foreach ($stmts as $stmt) {
			if ($stmt instanceof \PhpParser\Node\Stmt\Function_ && $stmt->name->name === 'testFunc') {
				$function = $stmt;
				break;
			}
		}
		
		$this->assertNotNull($function, 'Function testFunc should be found');
		
		// Push global scope
		$this->scopeStack->pushScope(new \BambooHR\Guardrail\Scope\Scope(true, true, false));
		
		// Enter the function
		$this->functionEvaluator->onEnter($function, $this->symbolTable, $this->scopeStack);
		
		// Check that $obj has a union type
		$objType = $this->scopeStack->getVarType('obj');
		$this->assertNotNull($objType, 'Parameter $obj should have a type from docblock');
		$this->assertInstanceOf(\PhpParser\Node\UnionType::class, $objType, 'Parameter $obj should have a UnionType');
		
		// The union should contain null, so the variable should be marked as nullable
		// This is handled by TypeComparer::ifAnyTypeIsNull() during analysis
		$hasNull = false;
		foreach ($objType->types as $type) {
			if ($type instanceof \PhpParser\Node\Identifier && strcasecmp($type->name, 'null') === 0) {
				$hasNull = true;
				break;
			}
		}
		$this->assertTrue($hasNull, 'Union type should contain null');
	}
	
	/**
	 * Test that a parameter with no docblock and no type hint has null type
	 */
	public function testNoDocBlockHasNullType(): void {
		$code = '<?php
		function testFunc($obj) {
			// $obj has no type information
		}
		';
		
		$stmts = $this->parseCodeWithDocBlocks($code);
		
		// Find the function
		$function = null;
		foreach ($stmts as $stmt) {
			if ($stmt instanceof \PhpParser\Node\Stmt\Function_ && $stmt->name->name === 'testFunc') {
				$function = $stmt;
				break;
			}
		}
		
		$this->assertNotNull($function, 'Function testFunc should be found');
		
		// Push global scope
		$this->scopeStack->pushScope(new \BambooHR\Guardrail\Scope\Scope(true, true, false));
		
		// Enter the function
		$this->functionEvaluator->onEnter($function, $this->symbolTable, $this->scopeStack);
		
		// Check that $obj has null type (unknown)
		$objType = $this->scopeStack->getVarType('obj');
		$this->assertNull($objType, 'Parameter $obj should have null type (no type information)');
	}
	
	/**
	 * Test that native type hint takes precedence over docblock
	 */
	public function testNativeTypeHintTakesPrecedence(): void {
		$code = '<?php
		class TestClass {
			public function method(): void {}
		}
		
		class OtherClass {
			public function method(): void {}
		}
		
		/**
		 * @param OtherClass $obj
		 */
		function testFunc(TestClass $obj) {
			// $obj should use native type hint (TestClass), not docblock (OtherClass)
		}
		';
		
		$stmts = $this->parseCodeWithDocBlocks($code);
		
		// Find the function
		$function = null;
		foreach ($stmts as $stmt) {
			if ($stmt instanceof \PhpParser\Node\Stmt\Function_ && $stmt->name->name === 'testFunc') {
				$function = $stmt;
				break;
			}
		}
		
		$this->assertNotNull($function, 'Function testFunc should be found');
		
		// Push global scope
		$this->scopeStack->pushScope(new \BambooHR\Guardrail\Scope\Scope(true, true, false));
		
		// Enter the function
		$this->functionEvaluator->onEnter($function, $this->symbolTable, $this->scopeStack);
		
		// Check that $obj has the native type (TestClass)
		$objType = $this->scopeStack->getVarType('obj');
		$this->assertNotNull($objType, 'Parameter $obj should have a type');
		$this->assertInstanceOf(\PhpParser\Node\Name::class, $objType, 'Parameter $obj should have a Name type');
		$this->assertEquals('TestClass', $objType->toString(), 'Parameter $obj should use native type hint');
	}
}
