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
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\Util;


class PropertyFetch extends BaseCheck
{
	private $typeInferer;

	function __construct(SymbolTable $symbolTable, OutputInterface $doc) {
		parent::__construct($symbolTable, $doc);
		$this->typeInferer = new TypeInferrer($symbolTable);
	}

	function getCheckNodeTypes() {
		return [ \PhpParser\Node\Expr\PropertyFetch::class];
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

			$property = Util::findAbstractedProperty($type, $node->name, $this->symbolTable );

			if(!$property) {
				// Unknown property, but maybe they use magic methods to retrieve.
				$hasGet = Util::findAbstractedMethod($type, "__get", $this->symbolTable);
				if (!$hasGet) {
					$method = Util::findAbstractedMethod($type, $node->name, $this->symbolTable);
					if ($method) {
						$this->emitError($fileName, $node, BaseCheck::TYPE_INCORRECT_DYNAMIC_CALL, "Attempt to fetch a property rather than call method " . $node->name);
					}

					static $reported = [];
					if (!isset($reported[$type . '::' . $node->name])) {
						$reported[$type . '::' . $node->name] = true;
						$this->emitError($fileName, $node, BaseCheck::TYPE_UNKNOWN_PROPERTY, "Accessing unknown property of $type::" . $node->name);
					}
				}
			} else if($property->getAccess()=="private" && (!$inside || !isset($inside->namespacedName) || strcasecmp($type, $inside->namespacedName)!=0)) {
				$this->emitError($fileName, $node, BaseCheck::TYPE_ACCESS_VIOLATION, "Attempt to fetch private property ".$node->name);
			} else if($property->getAccess()=="protected" && (!$inside || !isset($inside->namespacedName) || !$this->symbolTable->isParentClassOrInterface($type, $inside->namespacedName))) {
				$this->emitError($fileName, $node, BaseCheck::TYPE_ACCESS_VIOLATION, "Attempt to fetch protected property ".$node->name);
			}
		}
	}
}
