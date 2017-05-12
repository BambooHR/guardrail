<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeInferrer;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\ClassMethod;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\Util;
use PhpParser\Node\Expr\Variable;


class PropertyFetch extends BaseCheck
{
	private $typeInferer;

	function __construct(SymbolTable $symbolTable, OutputInterface $doc) {
		parent::__construct($symbolTable, $doc);
		$this->typeInferer = new TypeInferrer($symbolTable);
	}

	function getCheckNodeTypes() {
		return [ \PhpParser\Node\Expr\PropertyFetch::class ];
	}

	/**
	 * @param                                    $fileName
	 * @param \PhpParser\Node\Expr\PropertyFetch $node
	 */
	function run($fileName, $node, ClassLike $inside=null, Scope $scope=null) {
		$type = $this->typeInferer->inferType($inside, $node->var, $scope );
		if($type && $type[0]!='!' && !$this->symbolTable->ignoreType($type)) {

			if(!is_string($node->name)) {
				// Variable property name.  Yuck!
				return;
			}
			$method = Util::findAbstractedMethod($type, $node->name, $this->symbolTable );
			if($method) {
				$this->emitError($fileName, $node, BaseCheck::TYPE_INCORRECT_DYNAMIC_CALL, "Attempt to fetch a property rather than call method ".$node->name);
			}
			//echo "Access ".$node->var->name."->".$node->name."\n";
			//$property = Util::findProperty($inside,$node->name, $this->symbolTable);
			//if(!$property) {
				//$this->emitError($fileName, $node, "Unknown property", "Accessing unknown property of $inside->namespacedName: \$this->" . $node->name);
			//	return;
			//}
		}
	}
}
