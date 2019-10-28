<?php

namespace BambooHR\Guardrail\Checks;


use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeInferrer;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrayItem;

class SplatCheck extends BaseCheck {

	/** @var TypeInferrer */
	protected $typeInferer;

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
		return [ ArrayItem::class ];
	}

	/**
	 * run
	 *
	 * @param string                   $fileName The name of the file we are parsing
	 * @param Node                     $node     Instance of the Node
	 * @param Node\Stmt\ClassLike|null $inside   Instance of the ClassLike (the class we are parsing) [optional]
	 * @param Scope|null               $scope    Instance of the Scope (all variables in the current state) [optional]
	 *
	 * @return void
	 */
	public function run($fileName, Node $node, Node\Stmt\ClassLike $inside=null, Scope $scope = null) {
		if ($node instanceof ArrayItem) {
			if ($node->unpack) {
				list($type) = $this->typeInferer->inferType($inside, $node->value, $scope);
				if ($type != Scope::MIXED_TYPE && $type != Scope::ARRAY_TYPE && $type != Scope::UNDEFINED && strpos($type,"[]")===false) {
					if (strpos($type,'!')!==0 && !$this->symbolTable->isParentClassOrInterface(\Traversable::class, $type )) {
						$this->emitError($fileName, $node, ErrorConstants::TYPE_SPLAT_MISMATCH, "Can't use ... here.  Value is not an array or traversable.");
					}
				}
			}
		}
	}


}