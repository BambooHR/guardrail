<?php

// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps

namespace BambooHR\Guardrail\Evaluators;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeComparer;
use PhpParser\Node;

class Catch_ implements OnEnterEvaluatorInterface
{
	function getInstanceType(): array|string {
		return Node\Stmt\Catch_::class;
	}

	function onEnter(Node $node, SymbolTable $table, ScopeStack $scopeStack): void {
		/** @var Node\Stmt\Catch_ $catch */
		$catch = $node;
		if ($catch->var) {
			$name = strval($node->var->name);
			$scope = $scopeStack->getCurrentScope();

			if ($scope->getVarExists($name)) {
				$oldType = $scope->getVarType($name);
				$allPreviousTypesAreExceptions = !$oldType || TypeComparer::ifEveryType($oldType, fn($type)=>$table->isParentClassOrInterface("exception", strval($type) ));
				$newTypes = implode( ",", array_map( fn($type)=>TypeComparer::typeToString($type), $node->types));
				if (!$allPreviousTypesAreExceptions) {
					$scopeStack->getOutput()->emitError(
						__CLASS__,
						$scopeStack->getCurrentFile(),
						$node->getLine(),
						ErrorConstants::TYPE_EXCEPTION_DUPLICATE_VARIABLE,
						"Catch overwrites existing variable (\$$name " . TypeComparer::typeToString($oldType) . ") as $newTypes"
					);
				}
			}
			$node->var->setAttribute('assignment', true);
			$scopeStack->setVarType($name, TypeComparer::getUniqueTypes($node->types), $node->getLine());
			$scopeStack->setVarUsed($name);
		}
	}
}