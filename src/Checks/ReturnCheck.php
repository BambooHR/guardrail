<?php namespace BambooHR\Guardrail\Checks;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeComparer;
use BambooHR\Guardrail\Util;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
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
		return [ Return_::class, Function_::class, ClassMethod::class ];
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
		if ($node instanceof Function_ || $node instanceof ClassMethod) {
			$this->checkFunctionForMissingReturn($fileName, $node, $inside);
		} else if ($node instanceof Return_) {
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
					$this->emitError($fileName, $node, ErrorConstants::TYPE_SIGNATURE_RETURN, "Attempt to return a value from a ".TypeComparer::typeToString($returnType)." function $functionName");
					return;
				}
			} else if ($returnType && $node->expr == null) {
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
	 * Check if a function/method is missing a required return statement
	 *
	 * @param string                $fileName The file being checked
	 * @param Function_|ClassMethod $node     The function or method node
	 * @param ClassLike|null        $inside   The class we're inside of (if any)
	 *
	 * @return void
	 */
	protected function checkFunctionForMissingReturn($fileName, $node, ?ClassLike $inside = null) {
		$returnType = $node->getReturnType();

		if (!$returnType) {
			return;
		}

		if ($node instanceof ClassMethod && $node->isAbstract()) {
			return;
		}

		if ($node->stmts === null) {
			return;
		}

		if ($this->returnTypeAllowsNoReturn($returnType)) {
			return;
		}

		if (!Util::allBranchesExit($node->stmts)) {
			$functionName = $this->getFunctionName($node, $inside);
			$this->emitError($fileName, $node, ErrorConstants::TYPE_SIGNATURE_RETURN, "Function $functionName must return a value of type " . TypeComparer::typeToString($returnType));
		}
	}

	/**
	 * Check if a return type allows a function to have no return statement
	 *
	 * @param Node\Identifier|Node\Name|Node\ComplexType|null $returnType
	 *
	 * @return bool
	 */
	protected function returnTypeAllowsNoReturn($returnType): bool {
		if (!$returnType) {
			return true;
		}

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
		} else if ($insideFunc instanceof Node\Expr\Closure || $insideFunc instanceof Node\Expr\ArrowFunction) {
			$functionName = "anonymous function";
		} else if ($insideFunc instanceof Node\Stmt\ClassMethod) {
			$class = isset($inside->namespacedName) ? strval($inside->namespacedName) : "";
			$functionName = "$class::" . strval($insideFunc->name);
		}
		return $functionName;
	}
}