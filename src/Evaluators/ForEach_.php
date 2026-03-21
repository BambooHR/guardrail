<?php

// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps

namespace BambooHR\Guardrail\Evaluators;

use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeComparer;
use PhpParser\Node;
use PhpParser\Node\Expr\List_;
use PhpParser\Node\Expr\Variable;

class ForEach_ implements OnEnterEvaluatorInterface
{
	function getInstanceType(): array|string {

		return Node\Stmt\Foreach_::class;
	}

	/**
	 * Extract the element type from an array type that has templates (e.g., int[] -> int)
	 * 
	 * @param Node|null $iterableType The type of the iterable expression
	 * @return Node|null The element type, or null if not determinable
	 */
	private function getArrayElementType(?Node $iterableType): ?Node {
		if ($iterableType === null) {
			return null;
		}

		// Check if it's an array type with templates (e.g., array<int>)
		if ($iterableType instanceof Node\Identifier && 
			strcasecmp($iterableType->name, "array") == 0 &&
			$iterableType->getAttribute('templates') &&
			count($iterableType->getAttribute('templates')) > 0) {
			return $iterableType->getAttribute('templates')[0];
		}

		// Check if it's a Name (class) with templates (e.g., ArrayObject<Foo>)
		if ($iterableType instanceof Node\Name &&
			$iterableType->getAttribute('templates') &&
			count($iterableType->getAttribute('templates')) > 0) {
			return $iterableType->getAttribute('templates')[0];
		}

		return null;
	}

	function onEnter(Node $node, SymbolTable $table, ScopeStack $scopeStack): void {
		assert($node instanceof Node\Stmt\Foreach_);
		$valueVar = $node->valueVar;
		$keyVar = $node->keyVar;
		
		// Get the type of the iterable expression to extract element type
		$iterableType = $node->expr->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);
		$elementType = $this->getArrayElementType($iterableType);
		
		if ($keyVar instanceof Variable) {
			if (gettype($keyVar->name) == "string") {
				$keyVar->setAttribute('assignment', true);
				$scopeStack->setVarWritten($keyVar->name, $keyVar->getLine());
				$scopeStack->setVarType($keyVar->name, null, $keyVar->getLine());
				$scopeStack->setVarUsed($keyVar->name);
			}
		}
		if ($valueVar instanceof Variable) {
			if (gettype($valueVar->name) == "string") {
				$valueVar->setAttribute('assignment', true);
				$scopeStack->setVarWritten($valueVar->name, $valueVar->getLine());
				$scopeStack->setVarType($valueVar->name, $elementType, $valueVar->getLine());
				$scopeStack->setVarUsed($valueVar->name);
			}
		} else {
			if ($valueVar instanceof List_) {
			// Deal with traditional list($a,b,$c) style list.
				foreach ($valueVar->items as $var) {
					
					if ($var instanceof Node\Expr\ArrayItem && $var->key == null && $var->value instanceof Variable) {
						if (gettype($var->value->name) == "string") {
							$var->value->setAttribute('assignment', true);
							$scopeStack->setVarWritten(strval($var->value->name), $var->getLine());
							$scopeStack->setVarType(strval($var->value->name), $elementType, $var->getLine());
						}
					}
				}
			}
		}
	}
}
