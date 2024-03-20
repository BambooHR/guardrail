<?php namespace BambooHR\Guardrail\Checks;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\Util;
use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassLike;

/**
 * Class StaticPropertyFetchCheck
 *
 * @package BambooHR\Guardrail\Checks
 */
class StaticPropertyFetchCheck extends BaseCheck {

	/**
	 * StaticPropertyFetchCheck constructor.
	 *
	 * @param SymbolTable     $symbolTable Instance of SymbolTable
	 * @param OutputInterface $doc         Instance of OutputInterface
	 */
	function __construct(SymbolTable $symbolTable, OutputInterface $doc) {
		parent::__construct($symbolTable, $doc);
	}

	/**
	 * getCheckNodeTypes
	 *
	 * @return array
	 */
	public function getCheckNodeTypes() {
		return [ Node\Expr\StaticPropertyFetch::class ];
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
		if ($node instanceof Node\Expr\StaticPropertyFetch) {
			$class = $node->class;
			if ($class == "self" || $class == "static") {
				if (!$inside) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_SCOPE_ERROR, "Can't access using $class:: outside of a class");
					return;
				}
				$class = $inside->namespacedName;
			}

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
					if ($property->getAccess() == "private" && (!$inside || !isset($inside->namespacedName) || strcasecmp($inside->namespacedName, $property->getClass()->getName()) != 0)) {
						$this->emitError($fileName, $node, ErrorConstants::TYPE_ACCESS_VIOLATION, "Attempt to fetch private property " . $node->name);
					} else if ($property->getAccess() == "protected" && (!$inside || !isset($inside->namespacedName) || !$this->symbolTable->isParentClassOrInterface($property->getClass()->getName(), $inside->namespacedName))) {
						$this->emitError($fileName, $node, ErrorConstants::TYPE_ACCESS_VIOLATION, "Attempt to fetch protected property " . $node->name);
					}
				}
			}
		}
	}
}
