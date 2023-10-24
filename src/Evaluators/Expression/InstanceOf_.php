<?php

namespace BambooHR\Guardrail\Evaluators\Expression;

use BambooHR\Guardrail\Evaluators\ExpressionInterface;
use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeComparer;
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
			$varName = TypeComparer::getChainedPropertyFetchName($instanceOf->expr);
			$className = strval($instanceOf->class);
			$trueScope=$scopeStack->getCurrentScope()->getScopeClone();
			$falseScope=$trueScope->getScopeClone();
			$trueScope->setVarType($varName, TypeComparer::nameFromName($instanceOf->class), $node->getLine());
			$falseScope->setVarType($varName, TypeComparer::removeNamedOption($falseScope->getVarType($varName),$className), $node->getLine());
			$node->setAttribute('assertsTrue', $trueScope );
			$node->setAttribute('assertsFalse', $falseScope );
		}
		return TypeComparer::identifierFromName("bool" );
	}
}