<?php

namespace BambooHR\Guardrail;

use BambooHR\Guardrail\Abstractions\ClassInterface;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use InvalidArgumentException;
use PhpParser\ConstExprEvaluationException;
use PhpParser\ConstExprEvaluator;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Error;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;

/**
 * Enhances PHP-Parser's `ConstExprEvaluator` to evaluate constant expressions, with added
 * support for basic class constant lookups (e.g., `ExampleClass::EXAMPLE_CONSTANT`) resolved
 * from the symbol table.
 *
 * Currently, it shares the following limitations with `ConstExprEvaluator` and does not support:
 * - Magic constants (e.g., `__DIR__`, `__FILE__`).
 * - Global constants (e.g., `PHP_VERSION`), except for `null`, `true`, and `false`.
 * - Enums and most class constant lookups (e.g., `self::CONSTANT`, `"MyClass"::CONSTANT`, `MyClass":{$constantName}`).
 * - `new` expressions in constant contexts (PHP 8.1+).
 * - Property fetches on enums in constant contexts (PHP 8.2+).
 */
readonly class ConstantExpressionEvaluator {
	public function __construct(private SymbolTable $symbolTable) {}
	/**
	 * @throws ConstExprEvaluationException
	 */
	public function evaluate(Expr $expr): mixed {
		$evaluator = new ConstExprEvaluator(
			fn($unhandledExpr) => $this->fallbackEvaluator($unhandledExpr),
		);

		return $evaluator->evaluateSilently($expr);
	}

	/**
	 * @throws ConstExprEvaluationException
	 */
	private function fallbackEvaluator(Expr $expr) {
		if ($expr instanceof Expr\ClassConstFetch) {
			return $this->evaluateClassConstFetch($expr);
		} elseif ($expr instanceof Expr\ConstFetch) {
			return $this->evaluateConstFetch($expr);
		} else {
			throw new ConstExprEvaluationException();
		}
	}

	/**
	 * @throws ConstExprEvaluationException
	 */
	private function evaluateConstFetch(Expr\ConstFetch $expr): mixed {
		$constantName = $expr->name->toString();

		if (defined($constantName)) {
			return constant($constantName);
		}

		throw new ConstExprEvaluationException();
	}

	/**
	 * @throws ConstExprEvaluationException
	 * @throws InvalidArgumentException
	 */
	private function evaluateClassConstFetch(ClassConstFetch $expr) {
		$className = $this->resolveClassName($expr->class);
		$constantName = $this->resolveConstantName($expr->name);
		return $this->resolveValue($className, $constantName);
	}

	/**
	 * @throws ConstExprEvaluationException
	 */
	private function resolveClassName(Expr|Name $class): string {
		if ($class instanceof Name) {
			return match ($class->toString()) {
				"this", "self", "static", "parent" => throw new ConstExprEvaluationException(),
				default => $class->toString()
			};
		} else {
			return $this->evaluate($class);
		}
	}

	/**
	 * @throws ConstExprEvaluationException
	 */
	private function resolveConstantName(Expr|Error|Identifier $name): string {
		if ($name instanceof Identifier) {
			return $name->toString();
		} else {
			return $this->evaluate($name);
		}
	}

	/**
	 * @throws ConstExprEvaluationException
	 */
	private function resolveValue(string $className, string $constantName): mixed {
		if ($constantName == "class") {
			return $className;
		}

		/** @var ClassInterface $classInterface */
		$classInterface = $this->symbolTable->getAbstractedClass($className);

		return $this->evaluate($classInterface->getConstantValueExpression($constantName));
	}
}
