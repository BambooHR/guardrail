<?php

namespace BambooHR\Guardrail\Tests;

use BambooHR\Guardrail\Config;
use BambooHR\Guardrail\Evaluators\Catch_;
use BambooHR\Guardrail\Evaluators\If_;
use BambooHR\Guardrail\Evaluators\TryCatch;
use BambooHR\Guardrail\Metrics\MetricOutputInterface;
use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\Scope\Scope;
use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\InMemorySymbolTable;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

/**
 * Test variable assignment behavior in try/catch blocks
 */
class TestTryCatchVariableAssignment extends TestCase {
	
	private InMemorySymbolTable $symbolTable;
	private ScopeStack $scopeStack;
	private TryCatch $tryCatchEvaluator;
	private Catch_ $catchEvaluator;
	private If_ $ifEvaluator;
	
	protected function setUp(): void {
		$this->symbolTable = new InMemorySymbolTable('test');
		
		$output = $this->createMock(OutputInterface::class);
		$metricOutput = $this->createMock(MetricOutputInterface::class);
		$config = $this->createMock(Config::class);
		
		$this->scopeStack = new ScopeStack($output, $metricOutput, $config);
		$this->scopeStack->setCurrentFile('test.php');
		
		$this->tryCatchEvaluator = new TryCatch();
		$this->catchEvaluator = new Catch_();
		$this->ifEvaluator = new If_();
	}
	
	/**
	 * Parse PHP code and return the AST
	 */
	private function parseCode(string $code): array {
		$parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
		$stmts = $parser->parse($code);
		
		// Apply name resolution
		$traverser = new NodeTraverser();
		$traverser->addVisitor(new NameResolver());
		$stmts = $traverser->traverse($stmts);
		
		return $stmts;
	}
	
	/**
	 * Simulate traversing nodes with evaluators
	 */
	private function traverseWithEvaluators(array $nodes): void {
		foreach ($nodes as $node) {
			$this->traverseNode($node);
		}
	}
	
	private function traverseNode(Node $node): void {
		// onEnter
		if ($node instanceof Node\Stmt\If_) {
			$this->ifEvaluator->onEnter($node, $this->symbolTable, $this->scopeStack);
		} elseif ($node instanceof Node\Stmt\Else_) {
			$this->ifEvaluator->onEnter($node, $this->symbolTable, $this->scopeStack);
		} elseif ($node instanceof Node\Stmt\ElseIf_) {
			$this->ifEvaluator->onEnter($node, $this->symbolTable, $this->scopeStack);
		} elseif ($node instanceof Node\Stmt\TryCatch) {
			$this->tryCatchEvaluator->onEnter($node, $this->symbolTable, $this->scopeStack);
		} elseif ($node instanceof Node\Stmt\Catch_) {
			$this->tryCatchEvaluator->onEnter($node, $this->symbolTable, $this->scopeStack);
			$this->catchEvaluator->onEnter($node, $this->symbolTable, $this->scopeStack);
		} elseif ($node instanceof Node\Stmt\Finally_) {
			$this->tryCatchEvaluator->onEnter($node, $this->symbolTable, $this->scopeStack);
		}
		
		// Traverse children
		foreach ($node->getSubNodeNames() as $name) {
			$subNode = $node->$name;
			if ($subNode instanceof Node) {
				$this->traverseNode($subNode);
			} elseif (is_array($subNode)) {
				foreach ($subNode as $item) {
					if ($item instanceof Node) {
						$this->traverseNode($item);
					}
				}
			}
		}
		
		// Simulate variable assignments in expression statements
		if ($node instanceof Node\Stmt\Expression && $node->expr instanceof Node\Expr\Assign) {
			$assign = $node->expr;
			if ($assign->var instanceof Node\Expr\Variable && is_string($assign->var->name)) {
				$varName = $assign->var->name;
				$this->scopeStack->setVarType($varName, new Node\Name('mixed'), $node->getLine());
			}
		}
		
		// onExit
		if ($node instanceof Node\Stmt\TryCatch) {
			$this->tryCatchEvaluator->onExit($node, $this->symbolTable, $this->scopeStack);
		} elseif ($node instanceof Node\Stmt\If_) {
			$this->ifEvaluator->onExit($node, $this->symbolTable, $this->scopeStack);
		}
	}
	
	public function testVariableAssignedInBothTryAndCatchIsDefinitelySet() {
		$code = '<?php
		function test() {
			try {
				$ob = getValue();
			} catch (\Exception $e) {
				$ob = null;
			}
			return $ob;
		}';
		
		$stmts = $this->parseCode($code);
		$function = $stmts[0];
		
		// Create function scope
		$functionScope = new Scope(false, false, false, $function);
		$this->scopeStack->pushScope($functionScope);
		
		// Traverse the function body
		$this->traverseWithEvaluators($function->stmts);
		
		// Check that $ob exists and is not marked as mayBeUnset
		$scope = $this->scopeStack->getCurrentScope();
		$this->assertTrue($scope->getVarExists('ob'), 'Variable $ob should exist');
		
		$obVar = $scope->getVarObject('ob');
		$this->assertNotNull($obVar, 'Variable $ob should have a ScopeVar object');
		$this->assertFalse($obVar->mayBeUnset, 'Variable $ob should not be marked as mayBeUnset since it is assigned in both try and catch');
	}
	
	public function testVariableAssignedOnlyInTryIsMaybeUnset() {
		$code = '<?php
		function test() {
			try {
				$ob = getValue();
			} catch (\Exception $e) {
				// $ob not assigned here
			}
			return $ob;
		}';
		
		$stmts = $this->parseCode($code);
		$function = $stmts[0];
		
		// Create function scope
		$functionScope = new Scope(false, false, false, $function);
		$this->scopeStack->pushScope($functionScope);
		
		// Traverse the function body
		$this->traverseWithEvaluators($function->stmts);
		
		// Check that $ob exists but is marked as mayBeUnset
		$scope = $this->scopeStack->getCurrentScope();
		$this->assertTrue($scope->getVarExists('ob'), 'Variable $ob should exist');
		
		$obVar = $scope->getVarObject('ob');
		$this->assertNotNull($obVar, 'Variable $ob should have a ScopeVar object');
		$this->assertTrue($obVar->mayBeUnset, 'Variable $ob should be marked as mayBeUnset since it is not assigned in catch block');
	}
	
	public function testVariableAssignedOnlyInCatchIsMaybeUnset() {
		$code = '<?php
		function test() {
			try {
				doSomething();
			} catch (\Exception $e) {
				$ob = null;
			}
			return $ob;
		}';
		
		$stmts = $this->parseCode($code);
		$function = $stmts[0];
		
		// Create function scope
		$functionScope = new Scope(false, false, false, $function);
		$this->scopeStack->pushScope($functionScope);
		
		// Traverse the function body
		$this->traverseWithEvaluators($function->stmts);
		
		// Check that $ob exists but is marked as mayBeUnset
		$scope = $this->scopeStack->getCurrentScope();
		$this->assertTrue($scope->getVarExists('ob'), 'Variable $ob should exist');
		
		$obVar = $scope->getVarObject('ob');
		$this->assertNotNull($obVar, 'Variable $ob should have a ScopeVar object');
		$this->assertTrue($obVar->mayBeUnset, 'Variable $ob should be marked as mayBeUnset since it is not assigned in try block');
	}
	
	public function testCatchVariableIsNotNull() {
		$code = '<?php
		function test() {
			try {
				doSomething();
			} catch (\Throwable $e) {
				$message = $e->getMessage();
			}
		}';
		
		$stmts = $this->parseCode($code);
		$function = $stmts[0];
		
		// Create function scope
		$functionScope = new Scope(false, false, false, $function);
		$this->scopeStack->pushScope($functionScope);
		
		// Traverse the function body
		$this->traverseWithEvaluators($function->stmts);
		
		// Get the catch scope to check the exception variable
		$tryStmt = $function->stmts[0];
		$catchStmt = $tryStmt->catches[0];
		$catchScope = $catchStmt->getAttribute('catch-scope');
		
		$this->assertNotNull($catchScope, 'Catch scope should exist');
		$this->assertTrue($catchScope->getVarExists('e'), 'Exception variable $e should exist');
		
		$eVar = $catchScope->getVarObject('e');
		$this->assertNotNull($eVar, 'Exception variable $e should have a ScopeVar object');
		$this->assertFalse($eVar->mayBeNull, 'Exception variable $e should not be nullable');
	}
	
	public function testMultipleCatchBlocksAllAssignVariable() {
		$code = '<?php
		function test() {
			try {
				$ob = getValue();
			} catch (\RuntimeException $e) {
				$ob = null;
			} catch (\Exception $e) {
				$ob = false;
			}
			return $ob;
		}';
		
		$stmts = $this->parseCode($code);
		$function = $stmts[0];
		
		// Create function scope
		$functionScope = new Scope(false, false, false, $function);
		$this->scopeStack->pushScope($functionScope);
		
		// Traverse the function body
		$this->traverseWithEvaluators($function->stmts);
		
		// Check that $ob exists and is not marked as mayBeUnset
		$scope = $this->scopeStack->getCurrentScope();
		$this->assertTrue($scope->getVarExists('ob'), 'Variable $ob should exist');
		
		$obVar = $scope->getVarObject('ob');
		$this->assertNotNull($obVar, 'Variable $ob should have a ScopeVar object');
		$this->assertFalse($obVar->mayBeUnset, 'Variable $ob should not be marked as mayBeUnset since it is assigned in try and all catch blocks');
	}
	
	public function testMultipleCatchBlocksOneMissingAssignment() {
		$code = '<?php
		function test() {
			try {
				$ob = getValue();
			} catch (\RuntimeException $e) {
				// $ob not assigned here
			} catch (\Exception $e) {
				$ob = false;
			}
			return $ob;
		}';
		
		$stmts = $this->parseCode($code);
		$function = $stmts[0];
		
		// Create function scope
		$functionScope = new Scope(false, false, false, $function);
		$this->scopeStack->pushScope($functionScope);
		
		// Traverse the function body
		$this->traverseWithEvaluators($function->stmts);
		
		// Check that $ob exists but is marked as mayBeUnset
		$scope = $this->scopeStack->getCurrentScope();
		$this->assertTrue($scope->getVarExists('ob'), 'Variable $ob should exist');
		
		$obVar = $scope->getVarObject('ob');
		$this->assertNotNull($obVar, 'Variable $ob should have a ScopeVar object');
		$this->assertTrue($obVar->mayBeUnset, 'Variable $ob should be marked as mayBeUnset since one catch block does not assign it');
	}
	
	public function testTryCatchWithFinallyVariableAssignment() {
		$code = '<?php
		function test() {
			try {
				$ob = getValue();
			} catch (\Exception $e) {
				$ob = null;
			} finally {
				$cleanup = true;
			}
			return $ob;
		}';
		
		$stmts = $this->parseCode($code);
		$function = $stmts[0];
		
		// Create function scope
		$functionScope = new Scope(false, false, false, $function);
		$this->scopeStack->pushScope($functionScope);
		
		// Traverse the function body
		$this->traverseWithEvaluators($function->stmts);
		
		// Check that $ob exists and is not marked as mayBeUnset
		$scope = $this->scopeStack->getCurrentScope();
		$this->assertTrue($scope->getVarExists('ob'), 'Variable $ob should exist');
		
		$obVar = $scope->getVarObject('ob');
		$this->assertNotNull($obVar, 'Variable $ob should have a ScopeVar object');
		$this->assertFalse($obVar->mayBeUnset, 'Variable $ob should not be marked as mayBeUnset');
		
		// Check that $cleanup from finally block exists and is not mayBeUnset
		$this->assertTrue($scope->getVarExists('cleanup'), 'Variable $cleanup should exist');
		$cleanupVar = $scope->getVarObject('cleanup');
		$this->assertNotNull($cleanupVar, 'Variable $cleanup should have a ScopeVar object');
		$this->assertFalse($cleanupVar->mayBeUnset, 'Variable $cleanup from finally should not be marked as mayBeUnset');
	}
	
	public function testNestedTryCatchVariableAssignment() {
		$code = '<?php
		function test() {
			try {
				try {
					$inner = getValue();
				} catch (\RuntimeException $e) {
					$inner = null;
				}
				$outer = $inner;
			} catch (\Exception $e) {
				$outer = false;
			}
			return $outer;
		}';
		
		$stmts = $this->parseCode($code);
		$function = $stmts[0];
		
		// Create function scope
		$functionScope = new Scope(false, false, false, $function);
		$this->scopeStack->pushScope($functionScope);
		
		// Traverse the function body
		$this->traverseWithEvaluators($function->stmts);
		
		// Check that $outer exists and is not marked as mayBeUnset
		$scope = $this->scopeStack->getCurrentScope();
		$this->assertTrue($scope->getVarExists('outer'), 'Variable $outer should exist');
		
		$outerVar = $scope->getVarObject('outer');
		$this->assertNotNull($outerVar, 'Variable $outer should have a ScopeVar object');
		$this->assertFalse($outerVar->mayBeUnset, 'Variable $outer should not be marked as mayBeUnset');
	}
	
	public function testIfElseWithTryCatchInElse() {
		$code = '<?php
		function getAbstractedFunction($name) {
			$func = getFunction($name);
			if ($func) {
				$ob = new AbstractionFunction($func);
			} else {
				try {
					$refl = new ReflectionFunction($name);
					$ob = new ReflectedFunction($refl);
				} catch (\ReflectionException $exception) {
					$ob = null;
				}
			}
			return $ob;
		}';
		
		$stmts = $this->parseCode($code);
		$function = $stmts[0];
		
		// Create function scope
		$functionScope = new Scope(false, false, false, $function);
		$this->scopeStack->pushScope($functionScope);
		
		// Traverse the function body - this should process the if/else with try/catch
		$this->traverseWithEvaluators($function->stmts);
		
		// After traversal, check the function scope (not the current scope which might be a branch)
		$scope = $this->scopeStack->getCurrentScope();
		
		// The variable should exist after the if/else merge
		$this->assertTrue($scope->getVarExists('ob'), 'Variable $ob should exist after if/else merge');
		
		$obVar = $scope->getVarObject('ob');
		$this->assertNotNull($obVar, 'Variable $ob should have a ScopeVar object');
		$this->assertFalse($obVar->mayBeUnset, 'Variable $ob should not be marked as mayBeUnset since it is assigned in both if and else (which contains try/catch that assigns in both branches)');
	}
}
