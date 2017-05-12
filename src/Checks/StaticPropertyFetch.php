<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeInferrer;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassLike;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\Util;


class StaticPropertyFetch extends BaseCheck
{
	private $typeInferer;

	function __construct(SymbolTable $symbolTable, OutputInterface $doc) {
		parent::__construct($symbolTable, $doc);
		$this->typeInferer = new TypeInferrer($symbolTable);
	}

	function getCheckNodeTypes() {
		return [ StaticPropertyFetch::class ];
	}

	/**
	 * @param                                    $fileName
	 * @param \PhpParser\Node\Expr\StaticPropertyFetch $node
	 */
	function run($fileName, $node, ClassLike $inside=null, Scope $scope=null) {
		$class = $node->class;
		if($class instanceof Name && is_string($node->name)) {
			$property = Util::findAbstractedProperty($class, $node->name, $this->symbolTable );
			if(!$property) {
				$method = Util::findAbstractedMethod($class, $node->name, $this->symbolTable );
				if($method) {
					$this->emitError($fileName, $node, BaseCheck::TYPE_INCORRECT_STATIC_CALL, "Attempt to fetch a static property rather than call method ".$node->name);
				}

				static $reported = [];
				if(!isset($reported[$class.'::'.$node->name])) {
					$reported[$class.'::'.$node->name]=true;
					$this->emitError($fileName, $node, BaseCheck::TYPE_UNKNOWN_PROPERTY, "Accessing unknown property of $class::" . $node->name);
				}
			} else {
				if(!$property->isStatic()) {
					$this->emitError($fileName, $node, BaseCheck::TYPE_INCORRECT_STATIC_CALL, "Attempt to fetch a dynamic variable statically $class::".$node->name);
				}
			}
		}
	}
}
