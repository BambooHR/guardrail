<?php

namespace BambooHR\Guardrail\Evaluators\Expression;

use BambooHR\Guardrail\Evaluators\ExpressionInterface;
use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeComparer;
use BambooHR\Guardrail\Util;
use PhpParser\Node;
use PhpParser\Node\ComplexType;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Interface_;

class ClassConstFetch implements ExpressionInterface
{
	function getInstanceType(): string {
		return Node\Expr\ClassConstFetch::class;
	}

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): ?Node {
		/** @var Node\Expr\ClassConstFetch $expr */
		$expr = $node;
		if ($expr->name instanceof Node\Expr) {
			return null;
		}

		// Class constants can point to other class constants, so follow the chain.
		while ($expr instanceof Node\Expr\ClassConstFetch && $expr->class instanceof Node\Name) {
			if ($expr->name == "class") {
				return TypeComparer::identifierFromName("string");
			} else {
				$className = self::relativeClassName($scopeStack->getCurrentClass(), $expr->class);
				$expr = Util::findAbstractedConstantExpr($className, $expr->name, $table);

				// If we couldn't resolve the constant, break out of the loop
				if ($expr === null) {
					break;
				}

				// If Foo::member resolves to an enum EnumCase, then it
				// will come back to us a Name (Foo) representing the Enum class.
				//  We can just turn around and return that Name as the type.
				if ($expr instanceof Node\Name) {
					return $expr;
				}
			}
		}

		if ($expr instanceof Node\Identifier) {
			return $expr;
		} elseif ($expr instanceof Node\Name) {
			return $expr;
		} elseif ($expr instanceof ComplexType) {
			// PHP 8.3+ typed constants can have UnionType, IntersectionType, or NullableType
			return $expr;
		} elseif ($expr instanceof Scalar) {
			return \BambooHR\Guardrail\Evaluators\Expression\Scalar::inferScalar($expr);
		} elseif ($expr instanceof Node\Expr\ClassConstFetch) {
			// If we still have an unresolved ClassConstFetch, it means we couldn't resolve it
			// Return null to indicate unknown type rather than trying to infer incorrectly
			return null;
		} elseif ($expr !== null) {
			// Use shared type inference for common expression patterns
			$inferredType = Util::inferTypeFromExpression($expr);
			if ($inferredType !== null) {
				return $inferredType;
			}
		}
		return null;
	}


	private static function relativeClassName(?ClassLike $inside, string $name): string {
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
				} elseif ($inside instanceof Interface_) {
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
