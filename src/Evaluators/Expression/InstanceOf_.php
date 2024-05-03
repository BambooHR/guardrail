<?php

namespace BambooHR\Guardrail\Evaluators\Expression;

use BambooHR\Guardrail\Evaluators\ExpressionInterface;
use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeComparer;
use BambooHR\Guardrail\Util;
use PhpParser\Node;

class InstanceOf_ implements ExpressionInterface {
	function getInstanceType(): string {
		return Node\Expr\Instanceof_::class;
	}

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): ?Node {
		/** @var Node\Expr\Instanceof_ $instanceOf */
		$instanceOf = $node;
		if (
			(
				$instanceOf->expr instanceof Node\Expr\Variable ||
				$instanceOf->expr instanceof Node\Expr\PropertyFetch
			) &&
			$instanceOf->class instanceof Node\Name
		) {
			$className = $instanceOf->class;

			if (Util::isSelfOrStaticType($instanceOf->class)) {
				$class = $scopeStack->getCurrentClass();
				if ($class) {
					$className = $class->namespacedName ?? $class->name;
				}
			}
			$varName = TypeComparer::getChainedPropertyFetchName($instanceOf->expr);
			$trueScope = $scopeStack->getCurrentScope()->getScopeClone();
			$falseScope = $trueScope->getScopeClone();
			if ($varName !== "this") {
				$trueScope->setVarType($varName, $className, $node->getLine());
				$falseScope->setVarType($varName, TypeComparer::removeNamedOption($falseScope->getVarType($varName), strval($className)), $node->getLine());
				$node->setAttribute('assertsTrue', $trueScope);
				$node->setAttribute('assertsFalse', $falseScope);
			}
		}

		return TypeComparer::identifierFromName("bool" );
	}
}