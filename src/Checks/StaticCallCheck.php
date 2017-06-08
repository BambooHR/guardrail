<?php namespace BambooHR\Guardrail\Checks;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassLike;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\Util;

/**
 * Class StaticCallCheck
 *
 * @package BambooHR\Guardrail\Checks
 */
class StaticCallCheck extends BaseCheck {

	/**
	 * getCheckNodeTypes
	 *
	 * @return array
	 */
	public function getCheckNodeTypes() {
		return [StaticCall::class];
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
	public function run($fileName, Node $node, ClassLike $inside=null, Scope $scope = null) {
		if ($node instanceof StaticCall) {
			if ($node->class instanceof Name && gettype($node->name) == "string") {

				$name = $node->class->toString();
				if ($this->symbolTable->ignoreType($name)) {
					return;
				}
				$possibleDynamic = false;

				switch (strtolower($name)) {
					case 'self':
						$possibleDynamic = true;
					// Fall through
					case 'static':
						if (!$inside) {
							$this->emitError($fileName, $node, ErrorConstants::TYPE_SCOPE_ERROR, "Can't access using self:: outside of a class");
							return;
						}
						$name = $inside->namespacedName;
						break;
					case 'parent':
						if (!$inside) {
							$this->emitError($fileName, $node, ErrorConstants::TYPE_SCOPE_ERROR, "Can't access using parent:: outside of a class");
							return;
						}
						$possibleDynamic = true;
						if ($inside instanceof Node\Stmt\Class_) {
							if ($inside->extends) {
								$name = strval($inside->extends);
								break;
							}
						}
						$this->emitError($fileName, $node, ErrorConstants::TYPE_SCOPE_ERROR, "Can't access using parent:: in a class with no parent");
						return;
					default:
						if ($inside) {
							$currentClass = strval($inside->namespacedName);
							if ($this->symbolTable->isParentClassOrInterface($name, $currentClass)) {
								$possibleDynamic = true;
							}
						}
						break;
				}

				$this->incTests();
				if (!$this->symbolTable->isDefinedClass($name)) {
					if (!$this->symbolTable->ignoreType($name)) {
						$this->emitError($fileName, $node, ErrorConstants::TYPE_UNKNOWN_CLASS, "Static call to unknown class $name::" . $node->name);
					}
				} else {

					$method = Util::findAbstractedMethod($name, $node->name, $this->symbolTable);
					if ($node->name == "__construct" && !$method) {
						// Find a PHP 4 style constructor (function name == class name)
						$method = Util::findAbstractedMethod($name, $name, $this->symbolTable);
					}

					if (!$method) {
						if (!Util::findAbstractedMethod($name, "__callStatic", $this->symbolTable) &&
							(!$possibleDynamic || !Util::findAbstractedMethod($name, "__call", $this->symbolTable))
						) {
							$this->emitError($fileName, $node, ErrorConstants::TYPE_UNKNOWN_METHOD, "Unable to find method.  $name::" . $node->name);
						}
					} else {
						if (!$method->isStatic()) {
							if (!$scope->isStatic() && $possibleDynamic) {
								//	if ($node->name != "__construct" && $node->class != "parent") {
								// echo "Static call in $fileName " . $node->getLine() . "\n";
								//	}
							} else {
								$this->emitError($fileName, $node, ErrorConstants::TYPE_INCORRECT_DYNAMIC_CALL, "Attempt to call non-static method: $name::" . $node->name . " statically");
							}
						}
						$minimumParams = $method->getMinimumRequiredParameters();
						if (count($node->args) < $minimumParams) {
							$this->emitError($fileName, $node, ErrorConstants::TYPE_SIGNATURE_COUNT, "Static call to method $name::" . $node->name . " does not pass enough parameters (" . count($node->args) . " passed $minimumParams required)");
						}
					}
				}
			}
		}
	}
}