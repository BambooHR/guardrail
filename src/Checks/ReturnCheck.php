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
		/** @var Return_ $node */
		$type = $this->typeInferer->inferType($inside, $node->expr, $scope );

		$insideFunc = $scope->getInsideFunction();
		if ($inside && $insideFunc && $type) {
			$expectedType = $insideFunc->getReturnType();
			if (!in_array($type, [Scope::SCALAR_TYPE, Scope::MIXED_TYPE, Scope::UNDEFINED]) &&
				$type != "" &&
				$expectedType != "" &&
				!$this->symbolTable->isParentClassOrInterface($expectedType, $type)
			) {
				$class = isset($inside->namespacedName) ? strval($inside->namespacedName) : "";
				$functionName = strval($inside->name) ?: "anonymous function";
				if ($class) {
					$msg = "Variable returned from method $class::$functionName()";
				} else {
					$msg = "Variable returned from function $functionName()";
				}
				$msg .= " must be a $expectedType, returning $type";
				$this->emitError($fileName, $node, ErrorConstants::TYPE_SIGNATURE_RETURN, $msg );
			}
		}
	}
}