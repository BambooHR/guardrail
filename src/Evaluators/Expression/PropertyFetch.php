<?php

namespace BambooHR\Guardrail\Evaluators\Expression;

use BambooHR\Guardrail\Evaluators\ExpressionInterface;
use BambooHR\Guardrail\NodePatterns;
use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeComparer;
use BambooHR\Guardrail\Util;
use PhpParser\Node;

class PropertyFetch implements ExpressionInterface
{
	function getInstanceType(): array|string {
		return [
			Node\Expr\PropertyFetch::class,
			Node\Expr\NullsafePropertyFetch::class,
			Node\Expr\StaticPropertyFetch::class
		];
	}

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): ?Node {
		// Fetching a property doesn't assert anything until we cast it to a bool
		// in which case it asserts that null is no longer an option.
		$parent = $scopeStack->getParent();
		if (NodePatterns::parentNodeExpectsBool($parent, $node)) {
			$var = NodePatterns::getVariableOrPropertyName($node);
			if ($var) {
				$varScope = $scopeStack->getCurrentScope();
				$varScope->setVarType($var, TypeComparer::removeNullOption($varScope->getVarType($var)), $node->getLine());
				$node->setAttribute('assertsTrue', $varScope);
			}
		}

		if (($node instanceof Node\Expr\PropertyFetch || $node instanceof Node\Expr\NullsafePropertyFetch) && $node->name instanceof Node\Identifier) {
			/** @var Node\Expr\PropertyFetch $expr */
			$expr = $node;

			$resolvedType = null;
			$chainedName = TypeComparer::getChainedPropertyFetchName($expr);
			if ( $chainedName && $scopeStack->getVarType($chainedName)) {
				$resolvedType = $scopeStack->getVarType($chainedName);
			}

			$class = $this->getClass($expr, $scopeStack);
			if (!$resolvedType) {
				$resolvedType = $this->getProperty($class, $expr->name, $table);
			}
			if ($class !== null && $resolvedType !== null && $node instanceof Node\Expr\NullsafePropertyFetch) {
				$hadNullClass = TypeComparer::ifAnyTypeIsNull($class);
				if ($hadNullClass) {
					// Add null to the list of potential types if the class to the left of ?-> is potentially null
					$resolvedType = TypeComparer::getUniqueTypes(TypeComparer::identifierFromName("null"), $resolvedType);
				}
			}

			return $resolvedType;
		} elseif ($node instanceof Node\Expr\StaticPropertyFetch) {
			/** @var Node\Expr\StaticPropertyFetch $staticPropertyFetch */
			$staticPropertyFetch = $node;
			if ($staticPropertyFetch->class instanceof Node\Name && $staticPropertyFetch->name instanceof Node\Identifier) {
				return $this->getProperty($staticPropertyFetch->class, $staticPropertyFetch->name, $table);
			}
		}
		return null;
	}

	public function getProperty($class, Node\Identifier $name, SymbolTable $table) {
		$propName = strval($name);
		if ($propName != "") {
			$types = [];
			$unknown = false;
			TypeComparer::forEachType(
                $class,
				function ($class) use ($propName, &$types, &$unknown, $table) {
					$classDef = $table->getAbstractedClass($class);
					if ($classDef) {
						$prop = Util::findAbstractedProperty($class, $propName, $table);
						if ($prop) {
							$types[] = $prop->getType();
						} else {
							$unknown = true;
						}
					}
				}
			);
			if ($unknown) {
				return null;
			} else {
				return TypeComparer::getUniqueTypes(...$types);
			}
		}
		return null;
	}

	/**
	 * @param Node\Expr\PropertyFetch $expr
	 * @param ScopeStack              $scopeStack
	 * @return mixed|Node\ComplexType|Node\Identifier|Node\Name|string|null
	 */
	public function getClass(Node\Expr\PropertyFetch|Node\Expr\NullsafePropertyFetch $expr, ScopeStack $scopeStack): mixed {
		// 1. See if our scope has an inferred symbolic type.  ie: "$foo->bar->baz=int"
		$scopeName = TypeComparer::getChainedPropertyFetchName($expr->var);
		$scope = $scopeStack->getCurrentScope();
		if ($scopeName !== null && $scope->getVarType($scopeName)) {
			return $scope->getVarType($scopeName);
		}

		// 2. See if we have inferred what $expr is
		$inferred = $expr->var->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);
		return $inferred;
	}
}