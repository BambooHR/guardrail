<?php

namespace BambooHR\Guardrail\Evaluators;

use BambooHR\Guardrail\NodePatterns;
use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
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
		foreach(self::EXPRESSION_CLASSES as $className) {
			$this->instances[]=new $className;
		}
	}

	function getInstanceType(): array|string {
		return Node\Expr::class;
	}

	function onEnter(Node $node, SymbolTable $table, ScopeStack $scopeStack): void
	{
		if ($node->hasAttribute('push-scope-on-enter')) {
			$scopeStack->pushScope( $scopeStack->getCurrentScope()->getScopeClone());
		}
		if ($node->hasAttribute('swap-scope-on-enter')) {
			$scopeStack->swapTopTwoScopes();
		}

		if ($node instanceof Node\Expr\BinaryOp\BooleanOr) {
			$node->left->setAttribute('push-false-scope-on-leave',true);
			$node->right->setAttribute('pop-scope-on-enter',true);
		}

		if ($node instanceof Node\Expr\BinaryOp\BooleanAnd) {
			$node->left->setAttribute('merge-true-assert-on-leave', true);
		}

		if ($node instanceof Node\Expr\Ternary) {
			$branch = $scopeStack->getCurrentScope()->getScopeClone();
			$scopeStack->pushScope($branch);
			$cond = If_::getIfCond($node);
			$cond->setAttribute('grab-if-cond-scope-on-leave', true);
			$node->else->setAttribute('swap-scope-on-enter',true);
		}

		if($node instanceof Node\Expr\Assign) {
			$node->var->setAttribute('assignment',true);
		}

		if($node instanceof Node\Expr\Closure || $node instanceof Node\Expr\ArrowFunction) {
			FunctionLike::handleEnterFunctionLike($node, $scopeStack);
		}
	}


	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): void {
		if ($node->hasAttribute('pop-scope-on-leave')) {
			$scopeStack->popScope();
		}
		if ($node->hasAttribute('pop-and-scope-on-leave')) {
			$type=$scopeStack->popScope();
			$scopeStack->getCurrentScope()->merge($type);
		}

		if ($node instanceof Node\Expr\Ternary) {
			$scope = $scopeStack->popScope();
			$scopeStack->getCurrentScope()->merge($scope);
		}

		if ($node instanceof Node\Expr\Yield_ || $node instanceof Node\Expr\YieldFrom) {
			return;
		}
		$inferredType = $this->findInstance(get_class($node))->onExit($node, $table, $scopeStack);
		$node->setAttribute(TypeComparer::INFERRED_TYPE_ATTR, $inferredType);
		if($node->hasAttribute('push-false-scope-on-leave')) {
			if ($node->hasAttribute('assertsFalse')) {
				$scopeStack->pushScope($node->getAttribute('assertsFalse'));
			} else {
				$scopeStack->pushScope( $scopeStack->getCurrentScope()->getScopeClone() );
			}
		}
		if($node->hasAttribute('grab-if-cond-scope-on-leave')) {
			$parents= $scopeStack->getParentNodes();
			$if= $parents[ array_key_last($parents)];
			If_::pushIfScope($if, $scopeStack);
			//$scopeStack->getCurrentScope()->dump();
		}
		if ($node->hasAttribute('merge-true-assert-on-leave') && $node->hasAttribute('assertsTrue') ) {
			$scopeStack->popScope();
			$scopeStack->pushScope($node->getAttribute('assertsTrue'));
		}
	}

	function findInstance($class):ExpressionInterface {
		static $cache = [];
		if (isset($cache[$class])) {
			return $cache[$class];
		}

		foreach($this->instances as $instance) {
			$handles = $instance->getInstanceType();
			if (is_array($handles)) {
				foreach ($handles as $subType) {
					if(is_a($class, $subType, true)) {
						$cache[$class]=$instance;
						return $instance;
					}
				}
			} else {
				if (is_a($class, $handles,true)) {
					$cache[$class]=$instance;
					return $instance;
				}
			}
		}


		throw new \InvalidArgumentException("Unknown expression $class");
	}
}