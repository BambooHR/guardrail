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
			if (!$this->allPathsReturnOrThrow($node, false)) {
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
	 * Check if all code paths in a function either return a value or throw an exception
	 *
	 * @param Node\FunctionLike $func The function to check
	 *
	 * @return bool
	 */
	private function allPathsReturnOrThrow(Node\FunctionLike $func, $throwOnly): bool {
		$stmts = $func->getStmts();
		if (!$stmts) {
			return false;
		}
		return $this->statementsAllReturnOrThrow($stmts, $throwOnly);
	}

	/**
	 * Check if all code paths in a list of statements either return or throw
	 *
	 * @param array $stmts     List of statements
	 * @param bool  $throwOnly If true, only throw counts; if false, return or throw counts
	 *
	 * @return bool
	 */
	private function statementsAllReturnOrThrow(array $stmts, bool $throwOnly): bool {
		$lastStatement = $this->getLastNonNopStatement($stmts);

		if (!$lastStatement) {
			return false;
		} elseif ($lastStatement instanceof Node\Stmt\Return_) {
			return !$throwOnly;
		} elseif ($lastStatement instanceof Node\Stmt\Throw_) {
			return true;
		} elseif ($lastStatement instanceof Node\Stmt\Expression && $lastStatement->expr instanceof Node\Expr\Exit_) {
			return !$throwOnly;
		} elseif ($lastStatement instanceof Node\Stmt\Expression && $this->isCallToFunctionThatThrows($lastStatement->expr)) {
			return true;
		} elseif ($lastStatement instanceof Node\Stmt\If_) {
			return $this->allIfBranchesReturnOrThrow($lastStatement, $throwOnly);
		} elseif ($lastStatement instanceof Node\Stmt\Switch_) {
			return $this->allSwitchCasesReturnOrThrow($lastStatement, $throwOnly);
		} elseif ($lastStatement instanceof Node\Stmt\TryCatch) {
			return $this->allTryCatchBranchesReturnOrThrow($lastStatement, $throwOnly);
		} elseif ($lastStatement instanceof Node\Stmt\While_) {
			return $this->whileLoopReturnsOrThrows($lastStatement, $throwOnly);
		} elseif ($lastStatement instanceof Node\Stmt\Do_) {
			return $this->statementsAllReturnOrThrow($lastStatement->stmts, $throwOnly);
		} else {
			return false;
		}
	}

	/**
	 * Get the last non-Nop statement from a list of statements
	 *
	 * @param array $stmts The statements
	 *
	 * @return Node|null
	 */
	private function getLastNonNopStatement(array $stmts): ?Node {
		for (end($stmts); key($stmts) !== null; prev($stmts)) {
			$currentElement = current($stmts);
			if (!$currentElement instanceof Node\Stmt\Nop) {
				return $currentElement;
			}
		}
		return null;
	}

	/**
	 * Check if all branches of an if statement either return or throws (with options for throw only)
	 *
	 * @param Node\Stmt\If_ $ifStatement Instance of If_
	 * @param bool          $throwOnly   If true, only throw counts; if false, return or throw counts
	 *
	 * @return bool
	 */
	private function allIfBranchesReturnOrThrow(Node\Stmt\If_ $ifStatement, bool $throwOnly): bool {
		if ($this->isConstantTrue($ifStatement->cond)) {
			return $this->statementsAllReturnOrThrow($ifStatement->stmts, $throwOnly);
		}
		if (!$ifStatement->else) {
			return false;
		}
		if (!$this->statementsAllReturnOrThrow($ifStatement->stmts, $throwOnly)) {
			return false;
		}
		if (!$this->statementsAllReturnOrThrow($ifStatement->else->stmts, $throwOnly)) {
			return false;
		}
		if ($ifStatement->elseifs) {
			foreach ($ifStatement->elseifs as $elseIf) {
				if (!$this->statementsAllReturnOrThrow($elseIf->stmts, $throwOnly)) {
					return false;
				}
			}
		}
		return true;
	}

	/**
	 * Check if all cases of a switch statement either return or throw (with options for throw only)
	 *
	 * @param Node\Stmt\Switch_ $switchStatement Instance of Switch_
	 * @param bool              $throwOnly       If true, only throw counts; if false, return or throw counts
	 *
	 * @return bool
	 */
	private function allSwitchCasesReturnOrThrow(Node\Stmt\Switch_ $switchStatement, bool $throwOnly): bool {
		$hasDefault = false;
		foreach ($switchStatement->cases as $case) {
			if ($case->cond === null) {
				$hasDefault = true;
			}
			$stmts = $case->stmts;
			while (($last = end($stmts)) instanceof Node\Stmt\Break_ || $last instanceof Node\Stmt\Nop) {
				$stmts = array_slice($stmts, 0, -1);
			}
			if ($stmts && !$this->statementsAllReturnOrThrow($stmts, $throwOnly)) {
				return false;
			}
		}
		return $hasDefault;
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
	 * Check if all branches of a try-catch statement either return or throw (with options for throws only)
	 *
	 * @param Node\Stmt\TryCatch $tryCatch  Instance of TryCatch
	 * @param bool               $throwOnly If true, only throw counts; if false, return or throw counts
	 *
	 * @return bool
	 */
	private function allTryCatchBranchesReturnOrThrow(Node\Stmt\TryCatch $tryCatch, bool $throwOnly): bool {
		if ($tryCatch->finally && $this->statementsAllReturnOrThrow($tryCatch->finally->stmts, $throwOnly)) {
			return true;
		}

		// Otherwise, both try and all catch blocks must return or throw
		if (!$this->statementsAllReturnOrThrow($tryCatch->stmts, $throwOnly)) {
			return false;
		}

		foreach ($tryCatch->catches as $catch) {
			if (!$this->statementsAllReturnOrThrow($catch->stmts, $throwOnly)) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Check if a return type allows a function to have no return statement
	 *
	 * @param Node\Identifier|Node\Name|Node\ComplexType|null $returnType
	 *
	 * @return bool
	 */
	protected function returnTypeAllowsNoReturn($returnType): bool {
		$allowedTypes = ["void", "never", "mixed", "none"];
		foreach ($allowedTypes as $type) {
			if (TypeComparer::isNamedIdentifier($returnType, $type)) {
				return true;
			}
		}

		return false;
	}

	private function isConstantTrue($expr): bool {
		if ($expr instanceof Node\Expr\ConstFetch) {
			$name = strtolower($expr->name->toString());
			return $name === 'true';
		}
		return false;
	}

	private function whileLoopReturnsOrThrows(Node\Stmt\While_ $whileLoop, bool $throwOnly): bool {
		if ($this->isConstantTrue($whileLoop->cond)) {
			return $this->statementsAllReturnOrThrow($whileLoop->stmts, $throwOnly);
		}
		return false;
	}

	/**
	 * Check if an expression is a call to a function that never returns
	 *
	 * @param Node\Expr $expr The expression to check
	 *
	 * @return bool
	 */
	private function isCallToFunctionThatThrows(Node\Expr $expr): bool {
		if ($expr instanceof Node\Expr\FuncCall) {
			return $this->isFunctionCallThatThrows($expr);
		} elseif ($expr instanceof Node\Expr\MethodCall) {
			return $this->isMethodCallThatThrows($expr);
		} elseif ($expr instanceof Node\Expr\StaticCall) {
			return $this->isStaticCallThatThrows($expr);
		}
		return false;
	}

	/**
	 * Check if a function call always throws
	 *
	 * @param Node\Expr\FuncCall $funcCall The function call to check
	 *
	 * @return bool
	 */
	private function isFunctionCallThatThrows(Node\Expr\FuncCall $funcCall): bool {
		if (!$funcCall->name instanceof Node\Name) {
			return false;
		}

		$function = $this->symbolTable->getAbstractedFunction(strval($funcCall->name));
		if (!$function instanceof \BambooHR\Guardrail\Abstractions\FunctionAbstraction) {
			return false;
		}

		$functionNode = $this->getFunctionNodeFromAbstraction($function);
		return $functionNode && $this->allPathsReturnOrThrow($functionNode, true);
	}

	/**
	 * Check if an instance method call always throws
	 *
	 * @param Node\Expr\MethodCall $methodCall The method call to check
	 *
	 * @return bool
	 */
	private function isMethodCallThatThrows(Node\Expr\MethodCall $methodCall): bool {
		if (!$methodCall->name instanceof Node\Identifier) {
			return false;
		}

		$type = $methodCall->var->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);
		if (!$type) {
			return false;
		}

		$allThrow = false;
		TypeComparer::forEachType($type, function ($typeNode) use ($methodCall, &$allThrow) {
			$method = \BambooHR\Guardrail\Util::findAbstractedMethod(
				strval($typeNode),
				$methodCall->name,
				$this->symbolTable
			);
			if ($method instanceof \BambooHR\Guardrail\Abstractions\ClassMethod) {
				$methodNode = $this->getMethodNodeFromAbstraction($method);
				if ($methodNode && $this->allPathsReturnOrThrow($methodNode, true)) {
					$allThrow = true;
				}
			}
		});
		return $allThrow;
	}

	/**
	 * Check if a static method call always throws
	 *
	 * @param Node\Expr\StaticCall $staticCall The static call to check
	 *
	 * @return bool
	 */
	private function isStaticCallThatThrows(Node\Expr\StaticCall $staticCall): bool {
		if (!$staticCall->name instanceof Node\Identifier || !$staticCall->class instanceof Node\Name) {
			return false;
		}

		$method = \BambooHR\Guardrail\Util::findAbstractedMethod(
			strval($staticCall->class),
			$staticCall->name,
			$this->symbolTable
		);

		if (!$method instanceof \BambooHR\Guardrail\Abstractions\ClassMethod) {
			return false;
		}

		$methodNode = $this->getMethodNodeFromAbstraction($method);
		return $methodNode && $this->allPathsReturnOrThrow($methodNode, true);
	}

	/**
	 * Get the underlying function node from a FunctionAbstraction
	 *
	 * @param \BambooHR\Guardrail\Abstractions\FunctionAbstraction $function
	 *
	 * @return Node\Stmt\Function_|null
	 */
	private function getFunctionNodeFromAbstraction(\BambooHR\Guardrail\Abstractions\FunctionAbstraction $function): ?Node\Stmt\Function_ {
		$reflection = new \ReflectionClass($function);
		$property = $reflection->getProperty('function');
		$functionNode = $property->getValue($function);
		return $functionNode instanceof Node\Stmt\Function_ ? $functionNode : null;
	}

	/**
	 * Get the underlying method node from a ClassMethod abstraction
	 *
	 * @param \BambooHR\Guardrail\Abstractions\ClassMethod $method
	 *
	 * @return Node\Stmt\ClassMethod|null
	 */
	private function getMethodNodeFromAbstraction(\BambooHR\Guardrail\Abstractions\ClassMethod $method): ?Node\Stmt\ClassMethod {
		$reflection = new \ReflectionClass($method);
		$property = $reflection->getProperty('method');
		$methodNode = $property->getValue($method);
		return $methodNode instanceof Node\Stmt\ClassMethod ? $methodNode : null;
	}
}
