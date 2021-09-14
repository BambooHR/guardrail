<?php namespace BambooHR\Guardrail\Checks;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Abstractions\Property;
use BambooHR\Guardrail\Attributes;
use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeInferrer;
use PhpParser\Node;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Stmt\ClassLike;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\Util;

/**
 * Class PropertyFetch
 *
 * @package BambooHR\Guardrail\Checks
 */
class PropertyStoreCheck extends BaseCheck {

	/**
	 * @var TypeInferrer
	 */
	private $typeInferer;

	/**
	 * PropertyFetch constructor.
	 *
	 * @param SymbolTable     $symbolTable Instance of the SymbolTable
	 * @param OutputInterface $doc         Instance of the OutputInterface
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
	public function getCheckNodeTypes(): array {
		return [ Node\Expr\Assign::class ];
	}

	/**
	 * run
	 *
	 * @param string         $fileName The name of the file we are parsing
	 * @param Node           $node Instance of the Node
	 * @param ClassLike|null $inside Instance of the ClassLike (the class we are parsing) [optional]
	 * @param Scope|null     $scope Instance of the Scope (all variables in the current state) [optional]
	 *
	 * @return void
	 */
	public function run(string $fileName, Node $node, ClassLike $inside=null, Scope $scope=null) {
		if ($node instanceof Node\Expr\Assign && $node->var instanceof PropertyFetch && $node->var->name instanceof Node\Identifier) {
			list($leftType, $leftAttributes) = $this->typeInferer->inferType($inside, $node->var, $scope);
			list($rightType, $rightAttributes) = $this->typeInferer->inferType($inside, $node->expr, $scope);
			if ($leftType && $rightType && $leftType != $rightType && $leftType != Scope::MIXED_TYPE && $rightType != Scope::MIXED_TYPE) {
				if ($leftType[0] != "!" && $rightType[0] != "!") {
					if (!$this->symbolTable->isParentClassOrInterface($leftType, $rightType)) {
						$this->emitError($fileName, $node, ErrorConstants::TYPE_ASSIGN_MISMATCH, "Type mismatch can not assign $rightType into a $leftType");
					}
				} else if (!$this->isArray($leftType) && $this->isArray($rightType)) {
					$leftStr = Scope::nameFromConst($leftType);
					$rightStr = Scope::nameFromConst($rightType);
					$this->emitError($fileName, $node, ErrorConstants::TYPE_ASSIGN_MISMATCH, "Type mismatch can not assign $rightStr into a $leftStr");
				} else {
					//at least one of the parameters is scalar
					if ($leftType[0] == '!') {
						$leftType = Scope::nameFromConst(substr($leftType,0,2)) . substr($leftType, 2);
					}
					if ($rightType[0] == '!') {
						$rightType = Scope::nameFromConst(substr($rightType, 0, 2)) . substr($rightType,2);
					}

					if (!$this->isArray($leftType) && !$this->isArray($rightType) && $rightType !== $leftType) {
						$this->emitError($fileName, $node, ErrorConstants::TYPE_ASSIGN_MISMATCH_SCALAR, "Type mismatch can not assign $rightType into a $leftType");
					}
				}
			}
		}
	}

	private function isArray($type) {
		return $type == Scope::ARRAY_TYPE || strpos($type,"[]")!==false;
	}
}
