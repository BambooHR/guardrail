<?php

namespace BambooHR\Guardrail\Evaluators\Expression;

use BambooHR\Guardrail\Evaluators\ExpressionInterface;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeComparer;
use PhpParser\Node;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;

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

		// Try to get the constant value from global constants
		$constantName = (string)$expr->name;
		$defineFile = $table->getDefineFile($constantName);
		if ($defineFile) {
			$valueExpr = $this->getConstantValueFromFile($defineFile, $constantName);
			if ($valueExpr) {
				$typeName = $this->inferTypeFromValueExpr($valueExpr);
				if ($typeName) {
					return TypeComparer::identifierFromName($typeName);
				}
			}
		}

		if (defined($expr->name)) {
			// Guardrail doesn't declare any global constants.  Any that exist are from the runtime.
			return TypeComparer::identifierFromName("mixed");
		}

		if ($table->isDefined($expr->name)) {
			return TypeComparer::identifierFromName("mixed");
		}

		return TypeComparer::identifierFromName("mixed");
	}

	private function inferTypeFromValueExpr(Node\Expr $expr): ?string {
		if ($expr instanceof Node\Scalar\LNumber) {
			return "int";
		} elseif ($expr instanceof Node\Scalar\DNumber) {
			return "float";
		} elseif ($expr instanceof Node\Scalar\String_) {
			return "string";
		} elseif ($expr instanceof Node\Expr\Array_) {
			return "array";
		} elseif ($expr instanceof Node\Expr\ConstFetch) {
			$name = strtolower((string)$expr->name);
			if ($name === "true" || $name === "false") {
				return "bool";
			} elseif ($name === "null") {
				return "null";
			}
		}
		return null;
	}

	private function getConstantValueFromFile(string $fileName, string $constantName): ?Node\Expr {
		if (!file_exists($fileName)) {
			return null;
		}

		$contents = file_get_contents($fileName);
		$parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
		try {
			$stmts = $parser->parse($contents);
		} catch (\PhpParser\Error $error) {
			return null;
		}

		$traverser = new NodeTraverser();
		$traverser->addVisitor(new NameResolver());
		$stmts = $traverser->traverse($stmts);

		// Search for the constant definition
		foreach ($stmts as $stmt) {
			if ($stmt instanceof Node\Stmt\Const_) {
				foreach ($stmt->consts as $const) {
					if (strcasecmp((string)$const->namespacedName, $constantName) == 0) {
						return $const->value;
					}
				}
			} elseif ($stmt instanceof Node\Stmt\Expression && $stmt->expr instanceof Node\Expr\FuncCall) {
				$funcCall = $stmt->expr;
				$funcName = null;
				if (isset($funcCall->name)) {
					$funcName = $funcCall->name;
				}
				if ($funcName instanceof Node\Name) {
					if (strcasecmp($funcName->toString(), 'define') == 0) {
						if (count($funcCall->args) >= 2) {
							$nameArg = $funcCall->args[0]->value;
							if ($nameArg instanceof Node\Scalar\String_ && strcasecmp($nameArg->value, $constantName) == 0) {
								return $funcCall->args[1]->value;
							}
						}
					}
				}
			}
		}

		return null;
	}
}
