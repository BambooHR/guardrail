<?php

namespace BambooHR\Guardrail\Evaluators\Expression;

use BambooHR\Guardrail\Evaluators\ExpressionInterface;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeComparer;
use BambooHR\Guardrail\Util;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use Throwable;

class ConstFetch implements ExpressionInterface
{
	function getInstanceType(): string {
		return Node\Expr\ConstFetch::class;
	}

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): ?Node {
		/** @var Node\Expr\ConstFetch $constFetch */
		$constFetch = $node;
		return $this->getType($table, $constFetch);
	}

	function getType(SymbolTable $table, Node\Expr\ConstFetch $expr): ?Node\Identifier {
		return self::typeOfConstExpr($expr, $table) ?? TypeComparer::identifierFromName("mixed");
	}

	/**
	 * PHP type of a constant expression, or null when the value cannot be determined.
	 */
	static function typeOfConstExpr(Node\Expr $expr, SymbolTable $table): ?Node\Identifier {
		$seen = [];
		$value = self::evaluate($expr, $table, $seen);
		if ($value === self::unknown()) {
			return null;
		}
		return self::typeOfValue($value);
	}

	private static function typeOfValue(mixed $value): Node\Identifier {
		$valueExpr = Util::valueToExpression($value);
		if ($valueExpr instanceof Node\Scalar) {
			return Scalar::inferScalar($valueExpr);
		}
		if ($valueExpr instanceof Node\Expr\Array_) {
			return TypeComparer::identifierFromName("array");
		}
		if ($valueExpr instanceof Node\Expr\ConstFetch) {
			return TypeComparer::identifierFromName(strval($valueExpr->name));
		}
		return TypeComparer::identifierFromName("mixed");
	}

	/**
	 * Evaluate a constant expression the way PHP would. Returns unknown() when
	 * the value cannot be determined.
	 *
	 * @param Node\Expr           $expr
	 * @param SymbolTable         $table
	 * @param array<string, true> $seen
	 */
	private static function evaluate(Node\Expr $expr, SymbolTable $table, array &$seen): mixed {
		if ($expr instanceof Node\Scalar\LNumber || $expr instanceof Node\Scalar\DNumber || $expr instanceof Node\Scalar\String_) {
			return $expr->value;
		}
		if ($expr instanceof Node\Expr\Array_) {
			return self::evaluateArray($expr, $table, $seen);
		}
		if ($expr instanceof Node\Expr\ConstFetch) {
			return self::evaluateConstFetch($expr, $table, $seen);
		}
		if ($expr instanceof Node\Expr\Ternary) {
			return self::evaluateTernary($expr, $table, $seen);
		}
		if ($expr instanceof Node\Expr\UnaryMinus || $expr instanceof Node\Expr\UnaryPlus || $expr instanceof Node\Expr\BitwiseNot || $expr instanceof Node\Expr\BooleanNot) {
			return self::evaluateUnary($expr, $table, $seen);
		}
		if ($expr instanceof Node\Expr\BinaryOp) {
			return self::evaluateBinary($expr, $table, $seen);
		}
		return self::unknown();
	}

	/**
	 * @param Node\Expr\ConstFetch $expr
	 * @param SymbolTable          $table
	 * @param array<string, true>  $seen
	 */
	private static function evaluateConstFetch(Node\Expr\ConstFetch $expr, SymbolTable $table, array &$seen): mixed {
		$name = strval($expr->name);
		$lower = strtolower($name);
		if ($lower === "true") {
			return true;
		}
		if ($lower === "false") {
			return false;
		}
		if ($lower === "null") {
			return null;
		}

		$namespacedName = $expr->name->getAttribute("namespacedName");
		$namespacedName = $namespacedName ? strval($namespacedName) : "";
		// Unqualified names inside a namespace use the namespaced constant when
		// it exists, and only then fall back to the global constant.
		if ($namespacedName !== "" && strcasecmp($namespacedName, $name) !== 0 && $table->isDefined($namespacedName)) {
			return self::evaluateDeclared($table, $namespacedName, $seen);
		}
		if (Util::isInternalConstant($name)) {
			try {
				return constant($name);
			} catch (Throwable) {
				return self::unknown();
			}
		}
		if ($table->isDefined($name)) {
			return self::evaluateDeclared($table, $name, $seen);
		}
		return self::unknown();
	}

	/**
	 * @param SymbolTable         $table
	 * @param string              $name
	 * @param array<string, true> $seen
	 */
	private static function evaluateDeclared(SymbolTable $table, string $name, array &$seen): mixed {
		$key = strtolower($name);
		if (isset($seen[$key])) {
			return self::unknown();
		}
		$file = $table->adjustBasePath($table->getDefineFile($name));
		if ($file === "" || !is_file($file)) {
			return self::unknown();
		}
		$stmts = self::parsedFile($file);
		if ($stmts === null) {
			return self::unknown();
		}
		$valueExpr = self::findDeclaredValue($stmts, $name);
		if (!$valueExpr instanceof Node\Expr) {
			return self::unknown();
		}
		$seen[$key] = true;
		try {
			return self::evaluate($valueExpr, $table, $seen);
		} finally {
			unset($seen[$key]);
		}
	}

	/**
	 * @param Node\Expr\Array_    $expr
	 * @param SymbolTable         $table
	 * @param array<string, true> $seen
	 */
	private static function evaluateArray(Node\Expr\Array_ $expr, SymbolTable $table, array &$seen): mixed {
		$items = [];
		foreach ($expr->items as $item) {
			if ($item === null) {
				return self::unknown();
			}
			$value = self::evaluate($item->value, $table, $seen);
			if ($value === self::unknown()) {
				return self::unknown();
			}
			if ($item->unpack) {
				if (!is_array($value)) {
					return self::unknown();
				}
				foreach ($value as $spreadKey => $spreadValue) {
					if (is_int($spreadKey)) {
						$items[] = $spreadValue;
					} else {
						$items[$spreadKey] = $spreadValue;
					}
				}
				continue;
			}
			if ($item->key === null) {
				$items[] = $value;
				continue;
			}
			$key = self::evaluate($item->key, $table, $seen);
			if ($key === self::unknown() || is_array($key) || is_object($key)) {
				return self::unknown();
			}
			$items[$key] = $value;
		}
		return $items;
	}

	/**
	 * @param Node\Expr\Ternary   $expr
	 * @param SymbolTable         $table
	 * @param array<string, true> $seen
	 */
	private static function evaluateTernary(Node\Expr\Ternary $expr, SymbolTable $table, array &$seen): mixed {
		$cond = self::evaluate($expr->cond, $table, $seen);
		if ($cond === self::unknown()) {
			return self::unknown();
		}
		if ($cond) {
			return $expr->if === null ? $cond : self::evaluate($expr->if, $table, $seen);
		}
		return self::evaluate($expr->else, $table, $seen);
	}

	/**
	 * @param Node\Expr           $expr
	 * @param SymbolTable         $table
	 * @param array<string, true> $seen
	 */
	private static function evaluateUnary(Node\Expr $expr, SymbolTable $table, array &$seen): mixed {
		$value = self::evaluate($expr->expr, $table, $seen);
		if ($value === self::unknown()) {
			return self::unknown();
		}
		try {
			return match ($expr::class) {
				Node\Expr\UnaryMinus::class => -$value,
				Node\Expr\UnaryPlus::class => +$value,
				Node\Expr\BitwiseNot::class => ~$value,
				Node\Expr\BooleanNot::class => !$value,
				default => self::unknown(),
			};
		} catch (Throwable) {
			return self::unknown();
		}
	}

	/**
	 * @param Node\Expr\BinaryOp  $expr
	 * @param SymbolTable         $table
	 * @param array<string, true> $seen
	 */
	private static function evaluateBinary(Node\Expr\BinaryOp $expr, SymbolTable $table, array &$seen): mixed {
		$left = self::evaluate($expr->left, $table, $seen);
		$right = self::evaluate($expr->right, $table, $seen);
		if ($left === self::unknown() || $right === self::unknown()) {
			return self::unknown();
		}
		try {
			return match ($expr::class) {
				Node\Expr\BinaryOp\BitwiseOr::class => $left | $right,
				Node\Expr\BinaryOp\BitwiseAnd::class => $left & $right,
				Node\Expr\BinaryOp\BitwiseXor::class => $left ^ $right,
				Node\Expr\BinaryOp\ShiftLeft::class => $left << $right,
				Node\Expr\BinaryOp\ShiftRight::class => $left >> $right,
				Node\Expr\BinaryOp\Plus::class => $left + $right,
				Node\Expr\BinaryOp\Minus::class => $left - $right,
				Node\Expr\BinaryOp\Mul::class => $left * $right,
				Node\Expr\BinaryOp\Div::class => $left / $right,
				Node\Expr\BinaryOp\Mod::class => $left % $right,
				Node\Expr\BinaryOp\Pow::class => $left ** $right,
				Node\Expr\BinaryOp\Concat::class => $left . $right,
				Node\Expr\BinaryOp\BooleanAnd::class, Node\Expr\BinaryOp\LogicalAnd::class => $left && $right,
				Node\Expr\BinaryOp\BooleanOr::class, Node\Expr\BinaryOp\LogicalOr::class => $left || $right,
				Node\Expr\BinaryOp\LogicalXor::class => $left xor $right,
				Node\Expr\BinaryOp\Identical::class => $left === $right,
				Node\Expr\BinaryOp\NotIdentical::class => $left !== $right,
				Node\Expr\BinaryOp\Equal::class => $left == $right,
				Node\Expr\BinaryOp\NotEqual::class => $left != $right,
				Node\Expr\BinaryOp\Smaller::class => $left < $right,
				Node\Expr\BinaryOp\SmallerOrEqual::class => $left <= $right,
				Node\Expr\BinaryOp\Greater::class => $left > $right,
				Node\Expr\BinaryOp\GreaterOrEqual::class => $left >= $right,
				Node\Expr\BinaryOp\Spaceship::class => $left <=> $right,
				Node\Expr\BinaryOp\Coalesce::class => $left ?? $right,
				default => self::unknown(),
			};
		} catch (Throwable) {
			return self::unknown();
		}
	}

	/**
	 * @return Node\Stmt[]|null
	 */
	private static function parsedFile(string $file): ?array {
		static $lastFile = "";
		static $lastStmts = null;
		if ($lastFile === $file) {
			return $lastStmts;
		}
		$lastFile = $file;
		$contents = file_get_contents($file);
		if ($contents === false) {
			return $lastStmts = null;
		}
		try {
			$stmts = (new ParserFactory())->create(ParserFactory::PREFER_PHP7)->parse($contents);
		} catch (Error) {
			return $lastStmts = null;
		}
		if ($stmts === null) {
			return $lastStmts = null;
		}
		$traverser = new NodeTraverser();
		$traverser->addVisitor(new NameResolver());
		return $lastStmts = $traverser->traverse($stmts);
	}

	/**
	 * @param Node\Stmt[] $stmts
	 */
	private static function findDeclaredValue(array $stmts, string $name): ?Node\Expr {
		foreach ($stmts as $stmt) {
			if ($stmt instanceof Node\Stmt\Namespace_ && is_array($stmt->stmts)) {
				$found = self::findDeclaredValue($stmt->stmts, $name);
				if ($found !== null) {
					return $found;
				}
			} elseif ($stmt instanceof Node\Stmt\Const_) {
				foreach ($stmt->consts as $const) {
					$constName = strval($const->namespacedName ?? $const->name);
					if (strcasecmp($constName, $name) === 0) {
						return $const->value;
					}
				}
			} elseif (
				$stmt instanceof Node\Stmt\Expression &&
				$stmt->expr instanceof Node\Expr\FuncCall &&
				$stmt->expr->name instanceof Node\Name &&
				strcasecmp($stmt->expr->name->toString(), "define") === 0 &&
				isset($stmt->expr->args[0], $stmt->expr->args[1]) &&
				$stmt->expr->args[0]->value instanceof Node\Scalar\String_ &&
				strcasecmp($stmt->expr->args[0]->value->value, $name) === 0
			) {
				return $stmt->expr->args[1]->value;
			}
		}
		return null;
	}

	private static function unknown(): object {
		static $unknown;
		return $unknown ??= new \stdClass();
	}
}
