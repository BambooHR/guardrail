<?php namespace BambooHR\Guardrail\Checks;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeInferrer;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Return_;

/**
 * Class ReturnCheck
 *
 * @package BambooHR\Guardrail\Checks
 */
class ReturnCheck extends BaseCheck {

	/**
	 * @var TypeInferrer
	 */
	private $typeInferer;

	/**
	 * ReturnCheck constructor.
	 *
	 * @param SymbolTable     $symbolTable Instance of SymbolTable
	 * @param OutputInterface $doc         Instance OutputInterface
	 */
	public function __construct(SymbolTable $symbolTable, OutputInterface $doc) {
		parent::__construct($symbolTable, $doc);
		$this->typeInferer = new TypeInferrer($symbolTable);
	}

	/**
	 * getCheckNodeTypes
	 *
	 * @return array
	 */
	public function getCheckNodeTypes() {
		return [ Return_::class ];
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
	public function run($fileName, Node $node, ClassLike $inside = null, Scope $scope = null) {
		if ($node instanceof Return_) {
			/** @var Return_ $node */
			list($type) = $this->typeInferer->inferType($inside, $node->expr, $scope);

			$insideFunc = $scope->getInsideFunction();
			if ($insideFunc && $type) {
				$returnType = $insideFunc->getReturnType();
				$typeString = $returnType instanceof Node\NullableType ? strval($returnType->type) : strval($returnType);
				$expectedReturnType = Scope::constFromName($typeString);

				if ($type == Scope::NULL_TYPE && $typeString != "" && !($returnType instanceof Node\NullableType)) {
					$functionName = $this->getFunctionName($inside, $insideFunc);
					$msg = "Attempt to return NULL from a non-nullable function $functionName() returned from $functionName()";
					$this->emitError($fileName, $node, ErrorConstants::TYPE_SIGNATURE_RETURN, $msg);
					return;
				}

				// For now, we don't worry about checking returns of scalar types.
				if ($type != "" &&
					$type[0] != "!" &&
					$expectedReturnType != "" &&
					$expectedReturnType[0] != "!" &&
					strcasecmp($type,$expectedReturnType) != 0 &&
					!$this->isClosureCallableMix($expectedReturnType, $type) &&
					!$this->symbolTable->isParentClassOrInterface($expectedReturnType, $type)
				) {
					$functionName = $this->getFunctionName($inside, $insideFunc);
					$msg = "Value returned from $functionName()" .
						" must be a " . Scope::nameFromConst($expectedReturnType) .
						", returning " . Scope::nameFromConst($type);
					$this->emitError($fileName, $node, ErrorConstants::TYPE_SIGNATURE_RETURN, $msg);
				}
			}
		}
	}

	protected function isClosureCallableMix($expected, $provided) {
		return strcasecmp($expected,"callable") == 0 && strcasecmp($provided,"Closure") == 0;
	}

	/**
	 * @param ClassLike         $inside     The class we're inside of (if any)
	 * @param Node\FunctionLike $insideFunc The method we're inside of
	 * @return string
	 */
	protected function getFunctionName(ClassLike $inside = null, Node\FunctionLike $insideFunc) {
		$functionName = "";
		if ($insideFunc instanceof Node\Stmt\Function_) {
			$functionName = strval($insideFunc->name);
		} else if ($insideFunc instanceof Node\Expr\Closure) {
			$functionName = "anonymous function";
		} else if ($insideFunc instanceof Node\Stmt\ClassMethod) {
			$class = isset($inside->namespacedName) ? strval($inside->namespacedName) : "";
			$functionName = "$class::" . strval($insideFunc->name);
		}
		return $functionName;
	}
}