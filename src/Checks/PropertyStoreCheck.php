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
use PhpParser\Node\Stmt\Class_;
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
	public function run($fileName, Node $node, ?ClassLike $inside=null, ?Scope $scope=null) {
		if ($node instanceof Node\Expr\Assign && $node->var instanceof PropertyFetch && $node->var->name instanceof Node\Identifier) {

			$targetObject = $node->var->var->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);
			$valueType = $node->expr->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);
			$nodeVarName=strval($node->var->name);

			$types = [];
			TypeComparer::forEachType($targetObject, function($individualType) use ($nodeVarName, $fileName, $node, $inside,  &$types) {
				if ($individualType instanceof Node\Identifier || $individualType instanceof Node\Name) {
					$typeStr = strval($individualType);
					if ($this->symbolTable->isDefinedClass($typeStr)) {
						$property = Util::findAbstractedProperty($typeStr, $nodeVarName, $this->symbolTable);
						if ($property) {
							$types[] = $property->getType();
							if (
								($property->isReadOnly() || $property->getClass()->isReadOnly()) &&
								(!($inside instanceof Class_) || strcasecmp($inside?->namespacedName, $typeStr) != 0)
							) {
								$this->emitError($fileName, $node, ErrorConstants::TYPE_ACCESS_VIOLATION, "Attempt to set read only variable " . $typeStr . "->" . $nodeVarName);
							}
						}
					}
				}
			});

			$targetType=TypeComparer::getUniqueTypes(...$types);
			if (!$this->typeComparer->isCompatibleWithTarget($targetType, $valueType, $scope?->isStrict())) {
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
