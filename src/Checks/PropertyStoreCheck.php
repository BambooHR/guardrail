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
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Stmt\ClassLike;

/**
 * Class PropertyFetch
 *
 * @package BambooHR\Guardrail\Checks
 */
class PropertyStoreCheck extends BaseCheck {


	private TypeComparer $typeComparer;

	/**
	 * PropertyFetch constructor.
	 *
	 * @param SymbolTable     $symbolTable Instance of the SymbolTable
	 * @param OutputInterface $doc         Instance of the OutputInterface
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
		return [ Node\Expr\Assign::class ];
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
	public function run($fileName, Node $node, ClassLike $inside=null, Scope $scope=null) {
		if ($node instanceof Node\Expr\Assign && $node->var instanceof PropertyFetch && $node->var->name instanceof Node\Identifier) {

			$targetType = $node->var->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);
			$valueType = $node->expr->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);
			if (!$this->typeComparer->isCompatibleWithTarget($targetType, $valueType, $scope)) {
				if($targetType instanceof Node\Identifier && util::isScalarType(strval($targetType))) {
					$errorType = ErrorConstants::TYPE_ASSIGN_MISMATCH_SCALAR;
				} else {
					$errorType = ErrorConstants::TYPE_ASSIGN_MISMATCH;
				}
				$this->emitError($fileName, $node, $errorType, "Type mismatch can not assign " . TypeComparer::typeToString($valueType) . " into a " . TypeComparer::typeToString($targetType));
			}
		}
	}
}
