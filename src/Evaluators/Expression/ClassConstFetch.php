<?php

namespace BambooHR\Guardrail\Evaluators\Expression;

use BambooHR\Guardrail\Evaluators\ExpressionInterface;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeComparer;
use BambooHR\Guardrail\Util;
use PhpParser\Node;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Interface_;

class ClassConstFetch implements ExpressionInterface
{

	function getInstanceType(): string
	{
		return Node\Expr\ClassConstFetch::class;
	}

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): ?Node
	{
		/** @var Node\Expr\ClassConstFetch $expr */
		$expr = $node;
		if ($expr->name instanceof Node\Expr) {
			return null;
		}

		// Class constants can point to other class constants, so follow the chain.
		while($expr instanceOf Node\Expr\ClassConstFetch && $expr->class instanceof Node\Name) {
			if ($expr->name == "class") {
				return TypeComparer::identifierFromName("string");
			} else {
				$className = self::relativeClassName($scopeStack->getCurrentClass(), $expr->class);
				$expr = Util::findAbstractedConstantExpr($className, $expr->name, $table);
			}
		}

		if ($expr instanceof Scalar) {
			return \BambooHR\Guardrail\Evaluators\Expression\Scalar::inferScalar($expr);
		} else if ($expr instanceof Node\Expr\Array_) {
			return TypeComparer::identifierFromName("array");
		} else {
			return null;
		}
	}


	private static function relativeClassName(?ClassLike $inside, string $name):string {
		switch (strtolower($name)) {
			case 'self':
			case 'static':
				if (!$inside) {
					return "";
				}
				return $inside->namespacedName;
			case 'parent':
				if (!$inside) {
					return "";
				}
				if ($inside instanceof Class_) {
					$name = strval($inside->extends);
				} else if ($inside instanceof Interface_) {
					$name = strval($inside->extends);
				} else {
					$name = "";
				}
				return $name;
			default:
				return $name;
		}
	}
}