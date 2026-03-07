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
			if (!$this->allPathsReturnOrThrow($node)) {
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
	private function allPathsReturnOrThrow(Node\FunctionLike $func): bool {
		$stmts = $func->getStmts();
		if (!$stmts) {
			return false;
		}
		return $this->statementsAllReturnOrThrow($stmts);
	}

	/**
	 * Check if all code paths in a list of statements either return or throw
	 *
	 * @param array $stmts List of statements
	 *
	 * @return bool
	 */
	private function statementsAllReturnOrThrow(array $stmts): bool {
		$lastStatement = $this->getLastNonNopStatement($stmts);

		if (!$lastStatement) {
			return false;
		} elseif ($lastStatement instanceof Node\Stmt\Return_) {
			return true;
		} elseif ($lastStatement instanceof Node\Stmt\Throw_) {
			return true;
		} elseif ($lastStatement instanceof Node\Stmt\Expression && $lastStatement->expr instanceof Node\Expr\Exit_) {
			return true;
		} elseif ($lastStatement instanceof Node\Stmt\Expression && $this->isCallToFunctionThatThrows($lastStatement->expr)) {
			return true;
		} elseif ($lastStatement instanceof Node\Stmt\If_) {
			return $this->allIfBranchesReturnOrThrow($lastStatement);
		} elseif ($lastStatement instanceof Node\Stmt\Switch_) {
			return $this->allSwitchCasesReturnOrThrow($lastStatement);
		} elseif ($lastStatement instanceof Node\Stmt\TryCatch) {
			return $this->allTryCatchBranchesReturnOrThrow($lastStatement);
		} elseif ($lastStatement instanceof Node\Stmt\While_) {
			return $this->whileLoopReturnsOrThrows($lastStatement);
		} elseif ($lastStatement instanceof Node\Stmt\Do_) {
			return $this->doWhileLoopReturnsOrThrows($lastStatement);
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
	 * Check if all branches of an if statement either return or throw
	 *
	 * @param Node\Stmt\If_ $ifStatement Instance of If_
	 *
	 * @return bool
	 */
	private function allIfBranchesReturnOrThrow(Node\Stmt\If_ $ifStatement): bool {
		return $this->checkIfBranches($ifStatement, false);
	}

	/**
	 * Check if all cases of a switch statement either return or throw
	 *
	 * @param Node\Stmt\Switch_ $switchStatement Instance of Switch_
	 *
	 * @return bool
	 */
	private function allSwitchCasesReturnOrThrow(Node\Stmt\Switch_ $switchStatement): bool {
		return $this->checkSwitchCases($switchStatement, false);
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
	 * Check if all branches of a try-catch statement either return or throw
	 *
	 * @param Node\Stmt\TryCatch $tryCatch Instance of TryCatch
	 *
	 * @return bool
	 */
	private function allTryCatchBranchesReturnOrThrow(Node\Stmt\TryCatch $tryCatch): bool {
		return $this->checkTryCatchBranches($tryCatch, false);
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

	private function whileLoopReturnsOrThrows(Node\Stmt\While_ $whileLoop): bool {
		if ($this->isConstantTrue($whileLoop->cond)) {
			return $this->statementsAllReturnOrThrow($whileLoop->stmts);
		}
		return false;
	}

	private function doWhileLoopReturnsOrThrows(Node\Stmt\Do_ $doWhileLoop): bool {
		return $this->statementsAllReturnOrThrow($doWhileLoop->stmts);
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
			$name = $expr->name;
			if ($name instanceof Node\Name) {
				$function = $this->symbolTable->getAbstractedFunction(strval($name));
				if ($function) {
					// Check if the function body always throws (not just returns)
					if ($function instanceof \BambooHR\Guardrail\Abstractions\FunctionAbstraction) {
						// FunctionAbstraction wraps a Function_ node, we need to check if it always throws
						// We can use reflection to get the function property
						$reflection = new \ReflectionClass($function);
						$property = $reflection->getProperty('function');
						$functionNode = $property->getValue($function);
						if ($functionNode instanceof Node\Stmt\Function_) {
							return $this->allPathsThrow($functionNode);
						}
					}
				}
			}
		} elseif ($expr instanceof Node\Expr\MethodCall && $expr->name instanceof Node\Identifier) {
			// Handle instance method calls
			$type = $expr->var->getAttribute(\BambooHR\Guardrail\TypeComparer::INFERRED_TYPE_ATTR);
			if ($type) {
				$allThrow = false;
				TypeComparer::forEachType($type, function ($typeNode) use ($expr, &$allThrow) {
					$method = \BambooHR\Guardrail\Util::findAbstractedMethod(strval($typeNode), $expr->name, $this->symbolTable);
					if ($method && $method instanceof \BambooHR\Guardrail\Abstractions\ClassMethod) {
						$methodNode = $this->extractMethodNode($method);
						if ($methodNode && $this->allPathsThrow($methodNode)) {
							$allThrow = true;
						}
					}
				});
				return $allThrow;
			}
		} elseif ($expr instanceof Node\Expr\StaticCall && $expr->name instanceof Node\Identifier) {
			// Handle static method calls
			if ($expr->class instanceof Node\Name) {
				$className = strval($expr->class);
				$method = \BambooHR\Guardrail\Util::findAbstractedMethod($className, $expr->name, $this->symbolTable);
				if ($method && $method instanceof \BambooHR\Guardrail\Abstractions\ClassMethod) {
					$methodNode = $this->extractMethodNode($method);
					if ($methodNode) {
						return $this->allPathsThrow($methodNode);
					}
				}
			}
		}
		return false;
	}

	/**
	 * Check if all code paths in a function throw an exception (not return)
	 *
	 * @param Node\FunctionLike $func The function to check
	 *
	 * @return bool
	 */
	private function allPathsThrow(Node\FunctionLike $func): bool {
		$stmts = $func->getStmts();
		if (!$stmts) {
			return false;
		}
		return $this->statementsAllThrow($stmts);
	}

	/**
	 * Check if all code paths in a list of statements throw (not return)
	 *
	 * @param array $stmts List of statements
	 *
	 * @return bool
	 */
	private function statementsAllThrow(array $stmts): bool {
		$lastStatement = $this->getLastNonNopStatement($stmts);

		if (!$lastStatement) {
			return false;
		} elseif ($lastStatement instanceof Node\Stmt\Throw_) {
			return true;
		} elseif ($lastStatement instanceof Node\Stmt\Expression && $this->isCallToFunctionThatThrows($lastStatement->expr)) {
			return true;
		} elseif ($lastStatement instanceof Node\Stmt\If_) {
			return $this->allIfBranchesThrow($lastStatement);
		} elseif ($lastStatement instanceof Node\Stmt\Switch_) {
			return $this->allSwitchCasesThrow($lastStatement);
		} elseif ($lastStatement instanceof Node\Stmt\TryCatch) {
			return $this->allTryCatchBranchesThrow($lastStatement);
		} else {
			return false;
		}
	}

	/**
	 * Check if all branches of an if statement throw
	 *
	 * @param Node\Stmt\If_ $ifStatement Instance of If_
	 *
	 * @return bool
	 */
	private function allIfBranchesThrow(Node\Stmt\If_ $ifStatement): bool {
		return $this->checkIfBranches($ifStatement, true);
	}

	/**
	 * Check if all cases of a switch statement throw
	 *
	 * @param Node\Stmt\Switch_ $switchStatement Instance of Switch_
	 *
	 * @return bool
	 */
	private function allSwitchCasesThrow(Node\Stmt\Switch_ $switchStatement): bool {
		return $this->checkSwitchCases($switchStatement, true);
	}

	/**
	 * Check if all branches of a try-catch statement throw
	 *
	 * @param Node\Stmt\TryCatch $tryCatch Instance of TryCatch
	 *
	 * @return bool
	 */
	private function allTryCatchBranchesThrow(Node\Stmt\TryCatch $tryCatch): bool {
		return $this->checkTryCatchBranches($tryCatch, true);
	}

	/**
	 * Unified method to check if branches meet termination criteria
	 *
	 * @param Node\Stmt\If_ $ifStatement Instance of If_
	 * @param bool          $throwOnly   If true, only throw counts; if false, return or throw counts
	 *
	 * @return bool
	 */
	private function checkIfBranches(Node\Stmt\If_ $ifStatement, bool $throwOnly): bool {
		if ($this->isConstantTrue($ifStatement->cond)) {
			return $throwOnly ? $this->statementsAllThrow($ifStatement->stmts) : $this->statementsAllReturnOrThrow($ifStatement->stmts);
		}
		if (!$ifStatement->else) {
			return false;
		}
		if ($throwOnly) {
			if (!$this->statementsAllThrow($ifStatement->stmts)) {
				return false;
			}
			if (!$this->statementsAllThrow($ifStatement->else->stmts)) {
				return false;
			}
			if ($ifStatement->elseifs) {
				foreach ($ifStatement->elseifs as $elseIf) {
					if (!$this->statementsAllThrow($elseIf->stmts)) {
						return false;
					}
				}
			}
		} else {
			if (!$this->statementsAllReturnOrThrow($ifStatement->stmts)) {
				return false;
			}
			if (!$this->statementsAllReturnOrThrow($ifStatement->else->stmts)) {
				return false;
			}
			if ($ifStatement->elseifs) {
				foreach ($ifStatement->elseifs as $elseIf) {
					if (!$this->statementsAllReturnOrThrow($elseIf->stmts)) {
						return false;
					}
				}
			}
		}
		return true;
	}

	/**
	 * Unified method to check if switch cases meet termination criteria
	 *
	 * @param Node\Stmt\Switch_ $switchStatement Instance of Switch_
	 * @param bool              $throwOnly       If true, only throw counts; if false, return or throw counts
	 *
	 * @return bool
	 */
	private function checkSwitchCases(Node\Stmt\Switch_ $switchStatement, bool $throwOnly): bool {
		$hasDefault = false;
		foreach ($switchStatement->cases as $case) {
			if ($case->cond === null) {
				$hasDefault = true;
			}
			$stmts = $case->stmts;
			while (($last = end($stmts)) instanceof Node\Stmt\Break_ || $last instanceof Node\Stmt\Nop) {
				$stmts = array_slice($stmts, 0, -1);
			}
			if ($stmts) {
				$allTerminate = $throwOnly ? $this->statementsAllThrow($stmts) : $this->statementsAllReturnOrThrow($stmts);
				if (!$allTerminate) {
					return false;
				}
			}
		}
		return $hasDefault;
	}

	/**
	 * Unified method to check if try-catch branches meet termination criteria
	 *
	 * @param Node\Stmt\TryCatch $tryCatch  Instance of TryCatch
	 * @param bool               $throwOnly If true, only throw counts; if false, return or throw counts
	 *
	 * @return bool
	 */
	private function checkTryCatchBranches(Node\Stmt\TryCatch $tryCatch, bool $throwOnly): bool {
		if ($tryCatch->finally) {
			$finallyTerminates = $throwOnly ? $this->statementsAllThrow($tryCatch->finally->stmts) : $this->statementsAllReturnOrThrow($tryCatch->finally->stmts);
			if ($finallyTerminates) {
				return true;
			}
		}

		$tryTerminates = $throwOnly ? $this->statementsAllThrow($tryCatch->stmts) : $this->statementsAllReturnOrThrow($tryCatch->stmts);
		if (!$tryTerminates) {
			return false;
		}
		foreach ($tryCatch->catches as $catch) {
			$catchTerminates = $throwOnly ? $this->statementsAllThrow($catch->stmts) : $this->statementsAllReturnOrThrow($catch->stmts);
			if (!$catchTerminates) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Extract method node from a ClassMethod abstraction
	 *
	 * @param \BambooHR\Guardrail\Abstractions\ClassMethod $method
	 *
	 * @return Node\Stmt\ClassMethod|null
	 */
	private function extractMethodNode(\BambooHR\Guardrail\Abstractions\ClassMethod $method): ?Node\Stmt\ClassMethod {
		$reflection = new \ReflectionClass($method);
		$property = $reflection->getProperty('method');
		$methodNode = $property->getValue($method);
		return $methodNode instanceof Node\Stmt\ClassMethod ? $methodNode : null;
	}
}
