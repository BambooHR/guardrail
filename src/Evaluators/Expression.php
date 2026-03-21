<?php

namespace BambooHR\Guardrail\Evaluators;

use BambooHR\Guardrail\NodePatterns;
use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeInference\TypeAssertion;
use BambooHR\Guardrail\Evaluators\Expression as Expr;
use BambooHR\Guardrail\TypeComparer;
use PhpParser\Node;

class Expression implements OnExitEvaluatorInterface, OnEnterEvaluatorInterface
{
	const EXPRESSION_CLASSES = [
		Expr\Array_::class,
		Expr\ArrayDimFetch::class,
		Expr\FunctionLike::class,
		Expr\Assign::class,
		Expr\AssignOp::class,
		Expr\BinaryOperator::class,
		Expr\CallLike::class,
		Expr\Cast::class,
		Expr\ClassConstFetch::class,
		Expr\Clone_::class,
		Expr\ConstFetch::class,
		Expr\Empty_::class,
		Expr\Exit_::class,
		Expr\IncDec::class,
		Expr\InstanceOf_::class,
		Expr\Match_::class,
		Expr\NoOp::class,
		Expr\Print_::class,
		Expr\PropertyFetch::class,
		EXpr\Scalar::class,
		Expr\ShellExec::class,
		Expr\Ternary::class,
		Expr\UnaryMinus::class,
		Expr\Variable::class
	];

	/** @var ExpressionInterface[] */
	private $instances = [];

	function __construct() {
		foreach (self::EXPRESSION_CLASSES as $className) {
			$this->instances[] = new $className();
		}
	}

	function getInstanceType(): array|string {
		return Node\Expr::class;
	}

	function onEnter(Node $node, SymbolTable $table, ScopeStack $scopeStack): void {
		// For short-circuit operators, check if we need to apply narrowing from left side
		// This happens when entering the operator node AFTER the left side has been evaluated
		if (($node instanceof Node\Expr\BinaryOp\BooleanAnd || 
		     $node instanceof Node\Expr\BinaryOp\LogicalAnd) &&
		    $node->hasAttribute('left-evaluated')) {
			// Left side has been evaluated and we're about to evaluate right side
			if ($node->left->hasAttribute('assertsTrue')) {
				$narrowedScope = $node->left->getAttribute('assertsTrue');
				$this->applyAssertionsToScope($narrowedScope, $scopeStack->getCurrentScope());
			}
		} elseif (($node instanceof Node\Expr\BinaryOp\BooleanOr ||
		           $node instanceof Node\Expr\BinaryOp\LogicalOr) &&
		          $node->hasAttribute('left-evaluated')) {
			// Left side has been evaluated and we're about to evaluate right side
			if ($node->left->hasAttribute('assertsFalse')) {
				$narrowedScope = $node->left->getAttribute('assertsFalse');
				$this->applyAssertionsToScope($narrowedScope, $scopeStack->getCurrentScope());
			}
		}
		
		if ($node->hasAttribute('swap-scope-on-enter')) {
			$scopeStack->swapTopTwoScopes();
		}

		if ($node instanceof Node\Expr\Ternary) {
			$branch = $scopeStack->getCurrentScope()->getScopeClone();
			TypeAssertion::narrowTypes($node->cond, $branch, true);
			$scopeStack->pushScope($branch);
			$cond = If_::getIfCond($node);
			$cond?->setAttribute('grab-if-cond-scope-on-leave', true);
			$node->else->setAttribute('swap-scope-on-enter', true);
			$node->setAttribute('pop-scope-on-leave', true);
		}

		if ($node instanceof Node\Expr\Closure || $node instanceof Node\Expr\ArrowFunction) {
			FunctionLike::handleEnterFunctionLike($node, $scopeStack);
		}

		if ($node instanceof Node\Expr\Yield_ || $node instanceof Node\Expr\YieldFrom) {
			return;
		}

		$instance = $this->findInstance(get_class($node));
		if ($instance instanceof OnEnterEvaluatorInterface) {
			$instance->onEnter($node, $table, $scopeStack);
		}
	}

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): void {
		if ($node->hasAttribute('pop-scope-on-leave')) {
			$type = $scopeStack->popScope();
			$scopeStack->getCurrentScope()->merge($type);
		}

		if ($node instanceof Node\Expr\Yield_ || $node instanceof Node\Expr\YieldFrom) {
			return;
		}

		// Call child evaluator's onExit FIRST to set attributes like assertsTrue/assertsFalse
		$instance = $this->findInstance(get_class($node));
		if ($instance instanceof ExpressionInterface) {
			$inferredType = $instance->onExit($node, $table, $scopeStack);
			// Canonicalize the type to ensure consistent representation
			$inferredType = TypeComparer::canonicalizeType($inferredType);
			$node->setAttribute(TypeComparer::INFERRED_TYPE_ATTR, $inferredType);
		}
		
		// Pop short-circuit scope if this is a &&/|| node that had one pushed
		if ($node->hasAttribute('pop-short-circuit-scope')) {
			$scopeStack->popScope();
		}

		// Apply deferred if-condition narrowing after condition is fully evaluated
		if ($node->hasAttribute('if-narrow-on-leave')) {
			$ifNode = $node->getAttribute('if-narrow-on-leave');
			$currentScope = $scopeStack->getCurrentScope();
			TypeAssertion::narrowTypes($node, $currentScope, true);
			if ($ifNode instanceof Node) {
				$ifNode->setAttribute('if-then-scope', $currentScope);
			}
		}

		// NOW check if we need to apply short-circuit narrowing (after attributes are set)
		$parentNodes = $scopeStack->getParentNodes();
		if (!empty($parentNodes)) {
			$parent = end($parentNodes);
			
			if ($parent instanceof Node\Expr\BinaryOp && $parent->left === $node) {
				// Left side just completed - push a narrowed scope clone for right side evaluation
				if ($parent instanceof Node\Expr\BinaryOp\BooleanAnd || 
				    $parent instanceof Node\Expr\BinaryOp\LogicalAnd) {
					// For &&, push scope with truthy narrowing from left side
					$narrowed = $scopeStack->getCurrentScope()->getScopeClone();
					TypeAssertion::narrowTypes($node, $narrowed, true);
					$scopeStack->pushScope($narrowed);
					$parent->setAttribute('pop-short-circuit-scope', true);
				} elseif ($parent instanceof Node\Expr\BinaryOp\BooleanOr ||
				          $parent instanceof Node\Expr\BinaryOp\LogicalOr) {
					// For ||, push scope with falsy narrowing from left side
					$narrowed = $scopeStack->getCurrentScope()->getScopeClone();
					TypeAssertion::narrowTypes($node, $narrowed, false);
					$scopeStack->pushScope($narrowed);
					$parent->setAttribute('pop-short-circuit-scope', true);
				}
			}
		}

		if ($node->hasAttribute('push-false-scope-on-leave')) {
			if ($node->hasAttribute('assertsFalse')) {
				$scopeStack->pushScope($node->getAttribute('assertsFalse'));
			} else {
				$scopeStack->pushScope($scopeStack->getCurrentScope()->getScopeClone());
			}
		}
		if ($node->hasAttribute('grab-if-cond-scope-on-leave')) {
			$parents = $scopeStack->getParentNodes();
			$if = $parents[array_key_last($parents)];
			If_::pushIfScope($if, $scopeStack);
		}
		
		// Handle never-returning expressions in short-circuit operators
		if ($node instanceof Node\Expr\BinaryOp\BooleanOr || 
		    $node instanceof Node\Expr\BinaryOp\LogicalOr) {
			
			if ($this->expressionNeverReturns($node->right)) {
				// Right side never returns, so left must be truthy after expression
				if ($node->left->hasAttribute('assertsTrue')) {
					$this->applyAssertionsToScope(
						$node->left->getAttribute('assertsTrue'),
						$scopeStack->getCurrentScope()
					);
				}
			}
		}
		
		if ($node instanceof Node\Expr\BinaryOp\BooleanAnd || 
		    $node instanceof Node\Expr\BinaryOp\LogicalAnd) {
			
			if ($this->expressionNeverReturns($node->right)) {
				// Right side never returns, so left must be falsy after expression
				if ($node->left->hasAttribute('assertsFalse')) {
					$this->applyAssertionsToScope(
						$node->left->getAttribute('assertsFalse'),
						$scopeStack->getCurrentScope()
					);
				}
			}
		}
	}
	
	/**
	 * Apply assertions from one scope to another scope
	 */
	private function applyAssertionsToScope(\BambooHR\Guardrail\Scope\Scope $assertedScope, \BambooHR\Guardrail\Scope\Scope $targetScope): void {
		$changed = $assertedScope->getTypeChangedVars();
		foreach ($changed as $name => $var) {
			$targetScope->setVarType($name, $var->type, $var->modifiedLine);
			$currentVar = $targetScope->getVarObject($name);
			if ($currentVar && $var) {
				$currentVar->mayBeNull = $var->mayBeNull;
				$currentVar->mayBeUnset = $var->mayBeUnset;
			}
		}
	}
	
	/**
	 * Check if an expression never returns (exit, throw, or function with never return type)
	 */
	private function expressionNeverReturns(Node $expr): bool {
		// Check if it's an Exit_ node (exit/die)
		if ($expr instanceof Node\Expr\Exit_) {
			return true;
		}
		
		// Check if it's a throw expression
		if ($expr instanceof Node\Stmt\Throw_) {
			return true;
		}
		
		// Check if it's a function/method call with 'never' return type
		$returnType = $expr->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);
		if (TypeComparer::isNamedIdentifier($returnType, 'never')) {
			return true;
		}
		
		return false;
	}

	/** @throws \InvalidArgumentException */
	function findInstance($class): ?ExpressionInterface {
		static $cache = [];
		if (isset($cache[$class])) {
			return $cache[$class];
		}

		foreach ($this->instances as $instance) {
			$handles = $instance->getInstanceType();
			if (is_array($handles)) {
				foreach ($handles as $subType) {
					if (is_a($class, $subType, true)) {
						$cache[$class] = $instance;
						return $instance;
					}
				}
			} else {
				if (is_a($class, $handles, true)) {
					$cache[$class] = $instance;
					return $instance;
				}
			}
		}

		if (
			!is_a($class, Node\Expr\ArrayItem::class, true) &&
			!is_a($class, Node\Expr\ClosureUse::class, true)
		) {
			throw new \InvalidArgumentException("Unknown expression $class");
		}
		return null;
	}
}
