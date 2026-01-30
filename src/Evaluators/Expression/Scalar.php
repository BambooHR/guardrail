<?php

namespace BambooHR\Guardrail\Evaluators\Expression;

use BambooHR\Guardrail\Evaluators\ExpressionInterface;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeComparer;
use PhpParser\Node;
use PhpParser\Node\Scalar as ScalarExp;

class Scalar implements ExpressionInterface
{

	function getInstanceType(): string {
		return Node\Scalar::class;
	}

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): ?Node {
		/** @var ScalarExp $expr */
		$expr = $node;
		return self::inferScalar($expr);
	}

	static function inferScalar(ScalarExp $expr): Node\Identifier {
		if ($expr instanceof ScalarExp\LNumber) {
			$type = TypeComparer::identifierFromName("int");
		} elseif ($expr instanceof ScalarExp\DNumber) {
			$type = TypeComparer::identifierFromName("float");
		} elseif ($expr instanceof ScalarExp\String_) {
			$type = TypeComparer::identifierFromName("string");
		} elseif ($expr instanceof ScalarExp\Encapsed) {
			$type = TypeComparer::identifierFromName("string");
		} elseif ($expr instanceof ScalarExp\MagicConst\Line) {
			$type = TypeComparer::identifierFromName("int");
		} elseif ($expr instanceof ScalarExp\MagicConst) {
			$type = TypeComparer::identifierFromName("string");
		} else {
			$type = TypeComparer::identifierFromName("mixed");
		}
		return $type;
	}
}