<?php

namespace BambooHR\Guardrail\Checks;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\NodeVisitors\ForEachNode;
use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeComparer;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Return_;

/**
 * Class ReturnCheck
 *
 * @package BambooHR\Guardrail\Checks
 */
class ReturnCheck extends BaseCheck {
	private TypeComparer $typeComparer;
	/**
	 * ReturnCheck constructor.
	 *
	 * @param SymbolTable     $symbolTable Instance of SymbolTable
	 * @param OutputInterface $doc         Instance OutputInterface
	 */
	public function __construct(SymbolTable $symbolTable, OutputInterface $doc) {
		parent::__construct($symbolTable, $doc);
		$this->typeComparer = new TypeComparer($symbolTable);
	}

	/**
	 * getCheckNodeTypes
	 *
	 * @return array
	 */
	public function getCheckNodeTypes() {
		return [ Return_::class, Node\Stmt\Function_::class, Node\Stmt\ClassMethod::class ];
	}

	/**
	 * run
	 *
	 * @param string         $fileName The name of the file we are parsing
	 * @param Node           $node     Instance of the Node
	 * @param ClassLike|null $inside   Instance of the ClassLike (the class we are parsing) [optional]
	 * @param Scope|null     $scope    Instance of the Scope (all variables in the current state) [optional]
	 *
	 * @return void
	 */
	public function run($fileName, Node $node, ?ClassLike $inside = null, ?Scope $scope = null) {
		if ($node instanceof Node\Stmt\Function_ || $node instanceof Node\Stmt\ClassMethod) {
			$this->checkGeneratorFunction($fileName, $node, $inside);
			return;
		}

		if ($node instanceof Return_) {
			$insideFunc = $scope?->getInsideFunction();

			if (!$insideFunc) {
				return;
			}

			$functionName = $this->getFunctionName($insideFunc, $inside);
			$returnType = $insideFunc->getReturnType();

			$returnIsVoid = TypeComparer::isNamedIdentifier($returnType, "void");
			$returnIsNever = TypeComparer::isNamedIdentifier($returnType, "never");
			if ($returnIsVoid || $returnIsNever) {
				if ($node->expr != null) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_SIGNATURE_RETURN, "Attempt to return a value from a " . TypeComparer::typeToString($returnType) . " function $functionName");
					return;
				}
			} elseif ($returnType && $node->expr == null) {
				$this->emitError($fileName, $node, ErrorConstants::TYPE_SIGNATURE_RETURN, "Attempt to return without a value in function $functionName");
				return;
			}
			if (!$node->expr) {
				return;
			}
			$exprType = $node->expr->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);
			if (!$exprType) {
				return;
			}

			if (TypeComparer::isNamedIdentifier($returnType, "self") && $inside) {
				$returnType = $inside->namespacedName;
			}

			if (TypeComparer::isNamedIdentifier($returnType, "Generator")) {
				return;
			}

			if (!$this->typeComparer->isCompatibleWithTarget($returnType, $exprType, $scope?->isStrict())) {
				$functionName = $this->getFunctionName($insideFunc, $inside);
				$msg = "Value returned from $functionName()" .
					" must be a " . TypeComparer::typeToString($returnType) .
					", returning " . TypeComparer::typeToString($exprType);

				$this->emitError($fileName, $node, ErrorConstants::TYPE_SIGNATURE_RETURN, $msg);
			}
		}
	}

	/**
	 * Validate function-level return type requirements
	 *
	 * @param string            $fileName The name of the file
	 * @param Node\FunctionLike $node     The function/method node
	 * @param ClassLike|null    $inside   The class we are inside of (if any)
	 *
	 * @return void
	 */
	private function checkGeneratorFunction(string $fileName, Node\FunctionLike $node, ?ClassLike $inside = null): void {
		$returnType = $node->getReturnType();

		if ($node instanceof Node\Stmt\ClassMethod && $node->isAbstract()) {
			return;
		}

		if ($inside instanceof Node\Stmt\Interface_) {
			return;
		}

		if (TypeComparer::isNamedIdentifier($returnType, "Generator")) {
			if (!$this->containsYield($node)) {
				$functionName = $this->getFunctionName($node, $inside);
				$this->emitError(
					$fileName,
					$node,
					ErrorConstants::TYPE_SIGNATURE_RETURN,
					"Function $functionName has Generator return type but does not contain yield"
				);
			}
			return;
		}

		if ($returnType && !$this->returnTypeAllowsNoReturn($returnType)) {
			if (!$this->containsReturn($node)) {
				$functionName = $this->getFunctionName($node, $inside);
				$this->emitError(
					$fileName,
					$node,
					ErrorConstants::TYPE_SIGNATURE_RETURN,
					"Function $functionName must return a value but contains no return statement"
				);
			}
		}
	}

	/**
	 * Check if a function contains yield or yield from statements
	 *
	 * @param Node\FunctionLike $func The function to check
	 *
	 * @return bool
	 */
	protected function containsYield(Node\FunctionLike $func): bool {
		$hasYield = false;

		$stmts = $func->getStmts();
		ForEachNode::run($stmts, function (Node $node) use (&$hasYield) {
			if ($node instanceof Node\Expr\Yield_ || $node instanceof Node\Expr\YieldFrom) {
				$hasYield = true;
			}
		});
		return $hasYield;
	}

	/**
	 * Check if a function contains a return statement
	 *
	 * @param Node\FunctionLike $func The function to check
	 *
	 * @return bool
	 */
	private function containsReturn(Node\FunctionLike $func): bool {
		$hasReturn = false;

		$stmts = $func->getStmts();
		ForEachNode::run($stmts, function (Node $node) use (&$hasReturn) {
			if ($node instanceof Node\Stmt\Return_) {
				$hasReturn = true;
			}
		});
		return $hasReturn;
	}

	/**
	 * @param Node\FunctionLike $insideFunc The method we're inside of
	 * @param ?ClassLike        $inside     The class we're inside of (if any)
	 *
	 * @return string
	 */
	protected function getFunctionName(Node\FunctionLike $insideFunc, ?ClassLike $inside = null) {
		$functionName = "";
		if ($insideFunc instanceof Node\Stmt\Function_) {
			$functionName = strval($insideFunc->name);
		} elseif ($insideFunc instanceof Node\Expr\Closure || $insideFunc instanceof Node\Expr\ArrowFunction) {
			$functionName = "anonymous function";
		} elseif ($insideFunc instanceof Node\Stmt\ClassMethod) {
			$class = isset($inside->namespacedName) ? strval($inside->namespacedName) : "";
			$functionName = "$class::" . strval($insideFunc->name);
		}
		return $functionName;
	}

	/**
	 * Check if a return type allows a function to have no return statement
	 *
	 * @param Node\Identifier|Node\Name|Node\ComplexType|null $returnType
	 *
	 * @return bool
	 */
	protected function returnTypeAllowsNoReturn($returnType): bool {
		$allowedTypes = ["void", "never", "mixed", "none", "null"];
		foreach ($allowedTypes as $type) {
			if (TypeComparer::isNamedIdentifier($returnType, $type)) {
				return true;
			}
		}

		if ($returnType instanceof Node\NullableType) {
			return true;
		}

		if ($returnType instanceof Node\UnionType) {
			foreach ($returnType->types as $type) {
				if (TypeComparer::isNamedIdentifier($type, "null")) {
					return true;
				}
			}
		}

		return false;
	}
}
