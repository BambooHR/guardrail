<?php

namespace BambooHR\Guardrail\Evaluators\Expression;

use BambooHR\Guardrail\Evaluators\ExpressionInterface;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeComparer;
use PhpParser\Node;

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
		if (strcasecmp($expr->name, "null") == 0) {
			return TypeComparer::identifierFromName("null");
		}
		if (strcasecmp($expr->name, "false") == 0 || strcasecmp($expr->name, "true") == 0) {
			return TypeComparer::identifierFromName($expr->name);
		}

		// Check if it's defined in the symbol table (user code)
		$defineFile = $table->getDefineFile($expr->name);
		if ($defineFile) {
			$constNode = $this->getConstFromFile($defineFile, $expr->name);
			if ($constNode !== null && $constNode->value !== null) {
				return $this->inferTypeFromValue($constNode->value);
			}
		}

		// Fall back to runtime constants (PHP built-ins)
		if (defined($expr->name)) {
			// Infer type from the actual constant value
			$value = constant($expr->name);
			if (is_int($value)) {
				return TypeComparer::identifierFromName("int");
			} elseif (is_float($value)) {
				return TypeComparer::identifierFromName("float");
			} elseif (is_string($value)) {
				return TypeComparer::identifierFromName("string");
			} elseif (is_bool($value)) {
				return TypeComparer::identifierFromName("bool");
			} elseif (is_array($value)) {
				return TypeComparer::identifierFromName("array");
			}
			return TypeComparer::identifierFromName("mixed");
		}

		return TypeComparer::identifierFromName("mixed");
	}

	/**
	 * Retrieve a constant definition from a file.
	 * Uses static caching to avoid re-parsing the same file multiple times.
	 */
	private function getConstFromFile(string $fileName, string $constName): ?Node\Const_ {
		static $fileCache = [];

		if (!isset($fileCache[$fileName])) {
			$stmts = $this->parseFile($fileName);
			if ($stmts === null) {
				return null;
			}
			$fileCache[$fileName] = $stmts;
		}

		return $this->findConstInStmts($fileCache[$fileName], $constName);
	}

	/**
	 * Parse a PHP file and return its AST with resolved names.
	 * Returns null on parse errors or file read failures.
	 */
	private function parseFile(string $fileName): ?array {
		$contents = @file_get_contents($fileName);
		if ($contents === false) {
			return null;
		}

		$parser = (new \PhpParser\ParserFactory())->create(\PhpParser\ParserFactory::PREFER_PHP7);
		try {
			$stmts = $parser->parse($contents);
		} catch (\PhpParser\Error $error) {
			return null;
		}

		$traverser = new \PhpParser\NodeTraverser();
		$traverser->addVisitor(new \PhpParser\NodeVisitor\NameResolver());
		return $traverser->traverse($stmts);
	}

	/**
	 * Recursively search for a constant definition in AST statements.
	 * Handles both top-level and namespaced constants.
	 */
	private function findConstInStmts(array $stmts, string $constName): ?Node\Const_ {
		foreach ($stmts as $stmt) {
			if ($stmt instanceof Node\Stmt\Const_) {
				foreach ($stmt->consts as $const) {
					if (
						isset($const->namespacedName) &&
						strcasecmp(strval($const->namespacedName), $constName) === 0
					) {
						return $const;
					}
				}
			} elseif ($stmt instanceof Node\Stmt\Namespace_) {
				$found = $this->findConstInStmts($stmt->stmts, $constName);
				if ($found !== null) {
					return $found;
				}
			}
		}
		return null;
	}

	/**
	 * Infer the type of a constant from its value expression.
	 * Handles scalar types, arrays, and special constants (true/false/null).
	 */
	private function inferTypeFromValue(Node\Expr $value): Node\Identifier {
		if ($value instanceof Node\Scalar\LNumber) {
			return TypeComparer::identifierFromName("int");
		}
		if ($value instanceof Node\Scalar\DNumber) {
			return TypeComparer::identifierFromName("float");
		}
		if ($value instanceof Node\Scalar\String_) {
			return TypeComparer::identifierFromName("string");
		}
		if ($value instanceof Node\Expr\ConstFetch) {
			$name = strtolower(strval($value->name));
			if ($name === 'true' || $name === 'false') {
				return TypeComparer::identifierFromName("bool");
			}
			if ($name === 'null') {
				return TypeComparer::identifierFromName("null");
			}
		}
		if ($value instanceof Node\Expr\Array_) {
			return TypeComparer::identifierFromName("array");
		}

		return TypeComparer::identifierFromName("mixed");
	}
}
