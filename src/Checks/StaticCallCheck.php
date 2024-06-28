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
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;

/**
 * Class StaticCallCheck
 *
 * @package BambooHR\Guardrail\Checks
 */
class StaticCallCheck extends CallCheck {
	/**
	 * MethodCall constructor.
	 *
	 * @param SymbolTable     $symbolTable Instance of the SymbolTable
	 * @param OutputInterface $doc         Instance of OutputInterface
	 */
	public function __construct(SymbolTable $symbolTable, OutputInterface $doc) {
		parent::__construct($symbolTable, $doc);
		$this->callableCheck = new CallableCheck($symbolTable, $doc);
	}

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
			$this->checkStaticCall($fileName, $node, $inside, $scope);
		}
	}

	/**
	 * checkAbstractClassMethod
	 *
	 * @param string     $fileName        The filename
	 * @param StaticCall $node            Instance of Node
	 * @param Scope      $scope           Instance of Scope
	 * @param string     $name            The name of the node
	 * @param bool       $possibleDynamic Is the node possibly dynamic
	 *
	 * @return void
	 */
	protected function checkAbstractClassMethod($fileName, StaticCall $node, ClassLike $inside = null, Scope $scope = null, $name, $possibleDynamic) {
		$method = Util::findAbstractedMethod($name, $node->name, $this->symbolTable);

		if ($node->name == "__construct" && ! $method) {
			// Find a PHP 4 style constructor (function name == class name)
			$method = Util::findAbstractedMethod($name, $name, $this->symbolTable);
		}
		if (! $method) {
			if (! Util::findAbstractedMethod($name, "__callStatic", $this->symbolTable) &&
				(! $possibleDynamic || ! Util::findAbstractedMethod($name, "__call", $this->symbolTable))
			) {
				$this->emitError($fileName, $node, ErrorConstants::TYPE_UNKNOWN_METHOD, "Unable to find method.  $name::" . $node->name);
			}
		} else {
			if (!$method->isStatic()) {
				if (!$scope->isStatic() && $possibleDynamic) {
					//if ($node->name != "__construct" && $node->class != "parent") {
					//	echo "Static call in $fileName " . $node->getLine() . "\n";
					//}
				} else {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_INCORRECT_DYNAMIC_CALL, "Attempt to call non-static method: $name::" . $node->name . " statically");
				}
			}

			if (!$node->isFirstClassCallable()) {
				$minimumParams = $method->getMinimumRequiredParameters();
				if (count($node->args) < $minimumParams) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_SIGNATURE_COUNT, "Static call to method $name::" . $node->name . " does not pass enough parameters (" . count($node->args) . " passed $minimumParams required)");
				}

				$this->checkParams($fileName, $node, $method->getName(), $scope, $inside, $node->args, $method->getParameters());
			}
		}
}

	/**
	 * checkDefaultSwitch
	 *
	 * @param ClassLike $inside Instance of ClassLike
	 * @param string    $name   The name of the node
	 *
	 * @return bool
	 */
	protected function checkDefaultSwitch(ClassLike $inside = null, $name) {
		$possibleDynamic = false;
		if ($inside) {
			$currentClass = isset($inside->namespacedName) ? strval($inside->namespacedName) : "";
			if ($this->symbolTable->isParentClassOrInterface($name, $currentClass)) {
				$possibleDynamic = true;
			}
		}
		return $possibleDynamic;
}

	/**
	 * checkStaticCall
	 *
	 * @param string     $fileName The name of the file
	 * @param StaticCall $node     Instance of StaticCall
	 * @param ClassLike  $inside   Instance of ClassLike
	 * @param Scope      $scope    Instance of Scope
	 *
	 * @return void
	 */
	protected function checkStaticCall($fileName, StaticCall $node, ClassLike $inside = null, Scope $scope = null) {
		if ($node->class instanceof Name && $node->name instanceof Node\Identifier) {
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
					if (! $inside) {
						$this->emitError($fileName, $node, ErrorConstants::TYPE_SCOPE_ERROR, "Can't access using self:: outside of a class");

						return;
					}
					$name = $inside->namespacedName;
					break;
				case 'parent':
					if (! $inside) {
						$this->emitError($fileName, $node, ErrorConstants::TYPE_SCOPE_ERROR, "Can't access using parent:: outside of a class");

						return;
					}
					$possibleDynamic = true;
					if ($inside instanceof Class_) {
						if ($inside->extends) {
							$name = strval($inside->extends);
							break;
						}
					}
					$this->emitError($fileName, $node, ErrorConstants::TYPE_SCOPE_ERROR, "Can't access using parent:: in a class with no parent");

					return;
				default:
					$possibleDynamic = $this->checkDefaultSwitch($inside, $name);
					break;
			}
			if (! $this->symbolTable->isDefinedClass($name)) {
				if (! $this->symbolTable->ignoreType($name)) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_UNKNOWN_CLASS, "Static call to unknown class $name::" . $node->name);
				}
			} else {
				$this->checkAbstractClassMethod($fileName, $node, $inside, $scope, $name, $possibleDynamic);
			}
		}
}
}