<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */


namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeInferrer;
use PhpParser\Node;

class ReturnCheck extends BaseCheck {
	private $typeInferer;

	function __construct(SymbolTable $symbolTable, OutputInterface $doc) {
		parent::__construct($symbolTable, $doc);
		$this->typeInferer = new TypeInferrer($symbolTable);
	}

	function getCheckNodeTypes() {
		return [ Node\Stmt\Return_::class ];
	}

	function run($fileName, $node, Node\Stmt\ClassLike $inside = null, Scope $scope = null) {
		/** @var Node\Stmt\Return_ $node */
		$type = $this->typeInferer->inferType($inside, $node->expr, $scope );

		$insideFunc = $scope->getInsideFunction();
		if($inside && $insideFunc && $type) {
			$expectedType = $insideFunc->getReturnType();
			if (!in_array($type, [Scope::SCALAR_TYPE, Scope::MIXED_TYPE, Scope::UNDEFINED]) &&
				$type != "" &&
				$expectedType != "" &&
				!$this->symbolTable->isParentClassOrInterface($expectedType, $type)
			) {
				$class = isset($inside->namespacedName) ? strval($inside->namespacedName) : "";
				$functionName = strval($inside->name) ?: "anonymous function";
				if($class) {
					$msg = "Variable returned from method $class::$functionName()";
				} else {
					$msg = "Variable returned from function $functionName()";
				}
				$msg.= " must be a $expectedType, returning $type";
				$this->emitError($fileName, $node, self::TYPE_SIGNATURE_RETURN, $msg );
			}
		}
	}
}