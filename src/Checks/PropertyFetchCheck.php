<?php

namespace BambooHR\Guardrail\Checks;

/**
 * Guardrail.  Copyright (c) 2016-2023, BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Abstractions\Property;
use BambooHR\Guardrail\NodePatterns;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\TypeComparer;
use BambooHR\Guardrail\Util;
use PhpParser\Node;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Stmt\ClassLike;

/**
 * Class PropertyFetch
 *
 * @package BambooHR\Guardrail\Checks
 */
class PropertyFetchCheck extends BaseCheck {
	/**
	 * getCheckNodeTypes
	 *
	 * @return array
	 */
	public function getCheckNodeTypes() {
		return [ PropertyFetch::class, Node\Expr\NullsafePropertyFetch::class ];
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
	public function run($fileName, Node $node, ?ClassLike $inside = null, ?Scope $scope = null) {
		if ($node instanceof PropertyFetch || $node instanceof Node\Expr\NullsafePropertyFetch) {

			$chainedName = NodePatterns::getVariableOrPropertyName($node);

			if (!$chainedName) {
				// Variable property name.  Yuck!
				return;
			}

			$chainedParent = substr($chainedName, 0, strrpos($chainedName, "->"));

			if ($scope?->getVarType($chainedParent)) {
				$type = $scope?->getVarType($chainedParent);
			} else {
				$type = $node->var->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);
			}

			if (!$node instanceof Node\Expr\NullsafePropertyFetch) {
				if (TypeComparer::ifAnyTypeIsNull($type) && !NodePatterns::parentIgnoresNulls($scope?->getParentNodes(), $node)) {
					$variable = TypeComparer::getChainedPropertyFetchName($node) ?? "";
					$this->emitError($fileName, $node, ErrorConstants::TYPE_NULL_DEREFERENCE, "Dereferencing potentially null object" . ($variable != "" ? " \$$variable" : ""));
				}
			}

			TypeComparer::forEachType($type, function ($type) use ($node, $fileName, $inside) {
				if ($type instanceof Node\Identifier || $type instanceof Node\Name) {
					$typeStr = strval($type);
					if ($typeStr && !$this->symbolTable->ignoreType($typeStr)) {
						if ($this->symbolTable->isParentClassOrInterface("SimpleXMLElement", $typeStr)) {
							// SimpleXMLElement has arbitrary properties based on the XML that was parsed.
							return;
						}
						$property = Util::findAbstractedProperty($typeStr, strval($node->name), $this->symbolTable);

						if (!$property) {
							$this->handleUndeclaredProperty($fileName, $node, $typeStr);
						} else {
							$this->handleDeclaredProperty($fileName, $node, $typeStr, $property, $property->getClass()->getName(), $inside);
						}
					}
				}
			});
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
	 * @param Property  $property   -
	 * @param string    $declaredIn -
	 * @param ClassLike $inside     -
	 * @return void
	 */
	private function handleDeclaredProperty($fileName, Node $node, $type, Property $property, $declaredIn, ?ClassLike $inside = null) {
		$access = $property->getAccess();

		if ($access == "protected" || $access == "private") {
			// It's ok to access a protected or private property if there is a __get method.
			$hasGet = Util::findAbstractedMethod($type, "__get", $this->symbolTable);
			if (!$hasGet) {
				$callingClass = $inside ? strval($inside->namespacedName) : "";
				if ($access === "private" && strcasecmp($declaredIn, $callingClass) !== 0) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_ACCESS_VIOLATION, "Attempt to fetch private property $declaredIn->" . $node->name . " from " .
											  (!$inside ? "outside a class" : $callingClass ));

				} elseif (
					$access == "protected" &&
					(
						$callingClass === ""
						||
						(
							Util::findAbstractedMethod($type, "__get", $this->symbolTable) ||
							!$this->symbolTable->isParentClassOrInterface($declaredIn, $callingClass)
						)
					)
				) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_ACCESS_VIOLATION, "Attempt to fetch protected property $declaredIn->" . $node->name . " from " .
											  (!$inside ? "outside a class" : $callingClass ));
				}
			}
		}
	}
}
