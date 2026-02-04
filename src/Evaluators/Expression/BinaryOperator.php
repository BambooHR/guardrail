<?php

namespace BambooHR\Guardrail\Evaluators\Expression;

use BambooHR\Guardrail\Evaluators\ExpressionInterface;
use BambooHR\Guardrail\NodePatterns;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeComparer;
use BambooHR\Guardrail\Util;
use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp;

class BinaryOperator implements ExpressionInterface {
	function getInstanceType(): string {
		return Node\Expr\BinaryOp::class;
	}

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): ?Node {
		/** @var Node\Expr\BinaryOp $expr */
		$expr = $node;
		$left = $expr->left->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);
		$right = $expr->right->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);
		$sigil = $expr->getOperatorSigil();

		if ($sigil == "&&" && $expr instanceof BinaryOp\BooleanAnd) {
			$this->mergeAndScope($expr, $scopeStack);
		} else if ($sigil == "||" && $expr instanceof BinaryOp\BooleanOr) {
			$this->mergeOrScope($expr);
		}

		if (in_array($sigil, ["===", "==", "!=", "!=="])) {
			$this->checkNullEquality($expr, $sigil, $scopeStack);
		}

		return match ($sigil) {
			"<=>" =>
				TypeComparer::identifierFromName("int"),
			"??" =>
				TypeComparer::getUniqueTypes(TypeComparer::removeNullOption($left), $right),
			"&", "|", "^" =>
				$this->handleBitMath($expr, $left, $right),
			"<<", ">>" =>
				TypeComparer::identifierFromName("int"),
			"." =>
				TypeComparer::identifierFromName("string"),
			"-","+","*","**" =>
				$this->handleBasicMath($expr, $left, $right),
			"<",">",">=","<=","==","!==","===","!=","&&","||","and","or","xor" =>
				TypeComparer::identifierFromName("bool"),
			"%" =>
				TypeComparer::identifierFromName("int"),
			"/" =>
				new Node\UnionType([TypeComparer::identifierFromName("int"), TypeComparer::identifierFromName("float")]),
			default => throw new \InvalidArgumentException("Unknown binary operator " . $sigil)
		};
	}

	function checkNullEquality(BinaryOp $node, string $sigil, ScopeStack $scope) {
		$varName = NodePatterns::getVariableOrPropertyName($node->right);
		if ($varName) {
			if ($node->left instanceof Node\Expr\ConstFetch && strcasecmp($node->left->name, "null") === 0) {
				$trueScope = $scope->getCurrentScope()->getScopeClone();
				$falseScope = $scope->getScopeClone();
				$this->handleNullEquivalency($sigil, $varName, $node, $trueScope, $falseScope);
			}
		} else {
			$varName = NodePatterns::getVariableOrPropertyName($node->left);
			if ($varName) {
				if ($node->right instanceof Node\Expr\ConstFetch && strcasecmp($node->right->name, "null") === 0) {
					$trueScope = $scope->getCurrentScope()->getScopeClone();
					$falseScope = $scope->getScopeClone();
					$this->handleNullEquivalency($sigil, $varName, $node, $trueScope, $falseScope);
				}
			}
		}
	}

	/**
	 * @param string      $sigil
	 * @param string      $varName
	 * @param BinaryOp    $node
	 * @param Scope\Scope $trueScope
	 * @param Scope\Scope $falseScope
	 * @return void
	 */
	public function handleNullEquivalency(string $sigil, string $varName, BinaryOp $node, Scope\Scope $trueScope, Scope\Scope $falseScope): void {
		switch ($sigil) {
			case "===":
				$trueScope->setVarType($varName, TypeComparer::identifierFromName("null"), $node->getLine());
				$falseScope->setVarType($varName, TypeComparer::removeNullOption($falseScope->getVarType($varName)), $node->getLine());
				break;
			case '==':
				$falseScope->setVarType($varName, TypeComparer::removeNullOption($falseScope->getVarType($varName)), $node->getLine());
				break;
			case '!==':
				$trueScope->setVarType($varName, TypeComparer::removeNullOption($trueScope->getVarType($varName)), $node->getLine());
				$falseScope->setVarType($varName, TypeComparer::identifierFromName("null"), $node->getLine());
				break;
			case '!=':
				$trueScope->setVarType($varName, TypeComparer::removeNullOption($trueScope->getVarType($varName)), $node->getLine());
				break;
		}
		$node->setAttribute('assertsTrue', $trueScope);
		$node->setAttribute('assertsFalse', $falseScope);
	}


	function mergeAndScope(BinaryOp\BooleanAnd $and, ScopeStack $scope) {
		if ($and->left->hasAttribute('assertsTrue') && $and->right->hasAttribute('assertsTrue')) {
			$left = $and->left->getAttribute('assertsTrue');
			$right = $and->right->getAttribute('assertsTrue');

			$current = $scope->getCurrentScope();
			$changed = $left->getTypeChangedVars();
			foreach ($changed as $name => $var) {
				$current->setVarType($name, $var->type, $var->modifiedLine);
			}

			$changed = $right->getTypeChangedVars();
			foreach ($changed as $name => $var) {
				$current->setVarType($name, $var->type, $var->modifiedLine);
			}

			$and->setAttribute('assertsTrue', $current);
		}
	}

	function mergeOrScope(BinaryOp\BooleanOr $or) {
		if ($or->left->getAttribute('assertsTrue') && $or->right->getAttribute('assertsTrue')) {
			/** @var Scope\Scope $left */
			$left = $or->left->getAttribute('assertsTrue');
			/** @var Scope\Scope $right */
			$right = $or->right->getAttribute('assertsTrue');

			$new = $left->getScopeClone();

			$leftChanged = $left->getTypeChangedVars();
			$rightChanged = $right->getTypeChangedVars();
			foreach ($leftChanged as $name => $var) {
				if (isset($rightChanged[$name])) {
					$newType = TypeComparer::getUniqueTypes($var->type, $rightChanged[$name]->type);
					$new->setVarType($name, $newType, $rightChanged[$name]->modifiedLine);
				}
			}
		}

		if ($or->left->getAttribute('assertsFalse') && $or->right->getAttribute('assertsFalse')) {
			/** @var Scope $right */
			$new = $or->left->getAttribute('assertsFalse')->getScopeClone();
			$right = $or->right->getAttribute('assertsFalse');
			$new->merge($right);
			$or->setAttribute('assertsFalse', $new);
		}
	}

	function handleBasicMath(BinaryOp $node, ?Node $left, ?Node $right) {
		if ($node->getOperatorSigil() == "+" && (TypeComparer::isNamedIdentifier($left, "array") || TypeComparer::isNamedIdentifier($right, "array"))) {
			return TypeComparer::identifierFromName("array");
		}

		if (TypeComparer::isNamedIdentifier($left, "float") || TypeComparer::isNamedIdentifier($right, "float")) {
			return TypeComparer::identifierFromName("float");
		}

		if (TypeComparer::isNamedIdentifier($left, "int") && TypeComparer::isNamedIdentifier($right, "int")) {
			return TypeComparer::identifierFromName("int");
		}

		if ($left == null || $right == null || TypeComparer::isNamedIdentifier($right, "mixed") || TypeComparer::isNamedIdentifier($left, "mixed")) {
			return TypeComparer::identifierFromName("mixed");
		}

		return new Node\UnionType([TypeComparer::identifierFromName("int"), TypeComparer::identifierFromName("float")]);
	}

	function handleBitMath(BinaryOp $node, ?Node $left, ?Node $right) {
		if (TypeComparer::isNamedIdentifier($left, "int") && TypeComparer::isNamedIdentifier($right, "int")) {
			return TypeComparer::identifierFromName("int");
		} else {
			return new Node\UnionType([TypeComparer::identifierFromName("int"), TypeComparer::identifierFromName("string")]);
		}
	}
}
