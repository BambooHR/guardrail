<?php

namespace BambooHR\Guardrail\Evaluators\Expression;

use BambooHR\Guardrail\Evaluators\ExpressionInterface;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeComparer;
use BambooHR\Guardrail\Util;
use MongoDB\BSON\Type;
use PhpParser\Node;

class ArrayDimFetch implements ExpressionInterface
{
	function getInstanceType(): array|string
	{
		return \PhpParser\Node\Expr\ArrayDimFetch::class;
	}

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): ?Node
	{
		/** @var Node\Expr\ArrayDimFetch $fetch */
		$fetch = $node;
		if ($fetch->dim == null) {
			$type = $fetch->var->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);
			if ($type instanceof Node\Identifier &&
				$type->getAttribute('templates') &&
				count($type->getAttribute('templates')) > 0 &&
				strcasecmp($type,"array")==0
			) {
				return $type->getAttribute('templates')[0];
			}
			if ($type instanceof Node\Name) {
				$specificTypes =$type->getAttribute('templates');
				if ($table->isParentClassOrInterface("ArrayAccess", $type)) {
					$class= $table->getClass($type);
					$method = Util::findAbstractedMethod($type, "offsetGet", $table);
					if ($type->getAttribute('templates') && count($type->getAttribute('templates'))>0) {
						$arrayType = $type->getAttribute('templates')[0];
						$returnType=$method->getDocBlockReturnType();
						return $this->substituteTemplateVars(['T'=> $arrayType], $returnType);
					}

				}
			}
			return TypeComparer::identifierFromName("array");
		} else {
			return null; // Todo: inspect the variable
		}
	}

	function substituteTemplateVars($vars, $value) {
		if ($value instanceof Node\Name) {
			if (isset($vars[strval($value)])) {
				return $vars[strval($value)];
			} else if ($value->getAttribute('templates')) {
				$templates=$value->getAttribute('templates');
				if (count($templates)==1) {
					return new Node\Name($value, ["templates" => [$templates[0]]]);
				}
			}
		}
		return $value;
	}
}