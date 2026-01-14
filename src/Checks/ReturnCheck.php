<?php namespace BambooHR\Guardrail\Checks;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

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
	 * Validate that a function with Generator return type contains yield
	 *
	 * @param string            $fileName The name of the file
	 * @param Node\FunctionLike $node     The function/method node
	 * @param ClassLike|null    $inside   The class we are inside of (if any)
	 *
	 * @return void
	 */
	private function checkGeneratorFunction(string $fileName, Node\FunctionLike $node, ?ClassLike $inside = null): void {
		$returnType = $node->getReturnType();

		if (!TypeComparer::isNamedIdentifier($returnType, "Generator")) {
			return;
		}

		if (!$this->containsYield($node)) {
			$functionName = $this->getFunctionName($node, $inside);
			$this->emitError($fileName, $node, ErrorConstants::TYPE_SIGNATURE_RETURN,
				"Function $functionName has Generator return type but does not contain yield");
		}
	}

	/**
	 * Check if a function is a generator (has Generator return type and contains yield)
	 *
	 * @param Node\Identifier|Node\Name|Node\ComplexType|null $returnType The return type
	 * @param Node\FunctionLike                               $insideFunc The function to check
	 *
	 * @return bool
	 */
	private function isGeneratorFunction($returnType, Node\FunctionLike $insideFunc): bool {
		if (!TypeComparer::isNamedIdentifier($returnType, "Generator")) {
			return false;
		}

		return $this->containsYield($insideFunc);
	}

	/**
	 * Check if a function contains a yield statement
	 *
	 * @param Node\FunctionLike $func The function to check
	 *
	 * @return bool
	 */
	private function containsYield(Node\FunctionLike $func): bool {
		$stmts = $func->getStmts();
		if (!$stmts) {
			return false;
		}

		$finder = new class {
			public bool $found = false;

			public function search(array $nodes): void {
				foreach ($nodes as $node) {
					if ($node instanceof Node\Expr\Yield_ || $node instanceof Node\Expr\YieldFrom) {
						$this->found = true;
						return;
					}
					if ($node instanceof Node) {
						foreach ($node->getSubNodeNames() as $name) {
							$subNode = $node->$name;
							if (is_array($subNode)) {
								$this->search($subNode);
							} else if ($subNode instanceof Node) {
								$this->search([$subNode]);
							}
							if ($this->found) {
								return;
							}
						}
					}
				}
			}
		};

		$finder->search($stmts);
		return $finder->found;
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