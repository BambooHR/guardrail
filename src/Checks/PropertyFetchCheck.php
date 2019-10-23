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
class PropertyFetchCheck extends BaseCheck {

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
	public function getCheckNodeTypes() {
		return [ PropertyFetch::class];
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
		if ($node instanceof PropertyFetch) {
			list($type, $attributes) = $this->typeInferer->inferType($inside, $node->var, $scope);
			if ($type && $type[0] != '!' && !$this->symbolTable->ignoreType($type)) {
				if ($attributes & Attributes::NULL_POSSIBLE) {
					$variable = ($node->var instanceof Node\Expr\Variable && is_string($node->var->name)) ? ' $' . $node->var->name : '';
					$this->emitError($fileName, $node, ErrorConstants::TYPE_NULL_DEREFERENCE, "Dereferencing potentially null object" . $variable);
				}

				if (!$node->name instanceof Node\Identifier) {
					// Variable property name.  Yuck!
					return;
				}

				if ($type == "SimpleXMLElement") {
					// SimpleXMLElement has arbitrary properties based on the XML that was parsed.
					return;
				}

				list($property,$declaredIn) = Util::findAbstractedProperty($type, strval($node->name), $this->symbolTable);

				if (!$property) {
					$this->handleUndeclaredProperty($fileName, $node, $type);
				} else {
					$this->handleDeclaredProperty($fileName, $node, $type, $inside, $property, $declaredIn);
				}
			}
		}
	}

	/**
	 * @param string $fileName -
	 * @param Node   $node     -
	 * @param string $type     -
	 * @return void
	 */
	private function handleUndeclaredProperty($fileName, Node $node, $type) {
		// Unknown property, but maybe they use magic methods to retrieve.
		$hasGet = Util::findAbstractedMethod($type, "__get", $this->symbolTable);
		if (!$hasGet) {
			$method = Util::findAbstractedMethod($type, $node->name, $this->symbolTable);
			if ($method) {
				$this->emitError($fileName, $node, ErrorConstants::TYPE_INCORRECT_DYNAMIC_CALL, "Attempt to fetch a property rather than call method " . $node->name);
			}

			static $reported = [];
			if (!isset($reported[$type . '::' . $node->name])) {
				//$reported[$type . '::' . $node->name] = true;
				$this->emitError($fileName, $node, ErrorConstants::TYPE_UNKNOWN_PROPERTY, "Accessing unknown property of $type::" . $node->name);
			}
		}
	}

	/**
	 * @param string    $fileName   -
	 * @param Node      $node       -
	 * @param string    $type       -
	 * @param ClassLike $inside     -
	 * @param Property  $property   -
	 * @param string    $declaredIn -
	 * @return void
	 */
	private function handleDeclaredProperty($fileName, Node $node, $type, ClassLike $inside = null, Property $property, $declaredIn) {
		$access = $property->getAccess();
		if ($access == "protected" || $access == "private") {
			// It's ok to access a protected or private property if there is a __get method.
			$hasGet = Util::findAbstractedMethod($type, "__get", $this->symbolTable);
			if (!$hasGet) {
				if ($access == "private" && (!$inside || !isset($inside->namespacedName) || strcasecmp($declaredIn, $inside->namespacedName) != 0)) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_ACCESS_VIOLATION, "Attempt to fetch private property " . $node->name);
				} else if ($access == "protected" && (!$inside || !isset($inside->namespacedName) || !$this->symbolTable->isParentClassOrInterface($declaredIn, $inside->namespacedName))) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_ACCESS_VIOLATION, "Attempt to fetch protected property " . $node->name);
				}
			}
		}
	}
}
