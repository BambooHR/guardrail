<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeInferrer;
use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassLike;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\Util;


class StaticPropertyFetch extends BaseCheck {
	private $typeInferer;

	function __construct(SymbolTable $symbolTable, OutputInterface $doc) {
		parent::__construct($symbolTable, $doc);
		$this->typeInferer = new TypeInferrer($symbolTable);
	}

	function getCheckNodeTypes() {
		return [ self::class ];
	}

	/**
	 * run
	 *
	 * @param string         $fileName The name of the file we are parsing
	 * @param Node           $node     Instance of the Node
	 * @param ClassLike|null $inside   Instance of the ClassLike (the class we are parsing) [optional]
	 * @param Scope|null     $scope    Instance of the Scope (all variables in the current state) [optional]
	 *
	 * @return mixed
	 */
	public function run($fileName, Node $node, ClassLike $inside=null, Scope $scope=null) {
		$class = $node->class;
		if ($class instanceof Name && is_string($node->name)) {
			$property = Util::findAbstractedProperty($class, $node->name, $this->symbolTable);
			if (!$property) {
				$method = Util::findAbstractedMethod($class, $node->name, $this->symbolTable);
				if ($method) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_INCORRECT_STATIC_CALL, "Attempt to fetch a static property rather than call method " . $node->name);
				}

				static $reported = [];
				if (!isset($reported[$class . '::' . $node->name])) {
					$reported[$class . '::' . $node->name] = true;
					$this->emitError($fileName, $node, ErrorConstants::TYPE_UNKNOWN_PROPERTY, "Accessing unknown property of $class::" . $node->name);
				}
			} else {
				if (!$property->isStatic()) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_INCORRECT_STATIC_CALL, "Attempt to fetch a dynamic variable statically $class::" . $node->name);
				}
				if ($property->getAccess() == "private" && (!$inside || !isset($inside->namespacedName) || strcasecmp($inside->namespacedName, $class) != 0)) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_ACCESS_VIOLATION, "Attempt to fetch private property " . $node->name);
				} else if ($property->getAccess() == "protected" && (!$inside || !isset($inside->namespacedName) || !$this->symbolTable->isParentClassOrInterface($class, $inside->namespacedName))) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_ACCESS_VIOLATION, "Attempt to fetch protected property " . $node->name);
				}
			}
		}
	}
}
