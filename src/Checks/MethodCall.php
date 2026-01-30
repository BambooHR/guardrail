<?php

namespace BambooHR\Guardrail\Checks;

/**
 * Guardrail.  Copyright (c) 2016-2024 BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Abstractions\MethodInterface;
use BambooHR\Guardrail\Metrics\Metric;
use BambooHR\Guardrail\Metrics\MetricOutputInterface;
use BambooHR\Guardrail\NodeVisitors\ForEachNode;
use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeComparer;
use BambooHR\Guardrail\Util;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Trait_;

/**
 * Class MethodCall
 *
 * @package BambooHR\Guardrail\Checks
 */
class MethodCall extends CallCheck {
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
		return [Expr\MethodCall::class, Expr\NullsafeMethodCall::class];
	}



	/**
	 * run
	 *
	 * @param string         $fileName The name of the file we are parsing
	 * @param Node           $node     Instance of the Node
	 * @param ClassLike|null $inside   Instance of the ClassLike (the class we are parsing) [optional]
	 * @param Scope|null     $scope    Instance of the Scope (all variables in the current state) [optional]
	 *
	 */
	public function run($fileName, Node $node, ?ClassLike $inside = null, ?Scope $scope = null) {
		if ($node instanceof Expr\MethodCall || $node instanceof Expr\NullsafeMethodCall) {
			if ($inside instanceof Trait_) {
				// Traits should be converted into methods in the class, so that we can check them in context.
				return;
			}
			if ($node->name instanceof Expr) {
				$this->emitError($fileName, $node, ErrorConstants::TYPE_VARIABLE_FUNCTION_NAME, "Variable function name detected");
				return;
			}
			$methodName = strval($node->name);

			$className = null;
			$var = $node->var;
			if ($var instanceof Variable) {
				if ($var->name == "this" && !$inside) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_SCOPE_ERROR, "Can't use \$this outside of a class");
					return;
				}
			}

			$name = TypeComparer::getChainedPropertyFetchName($var);
			if ($scope && $name && $scope->getVarType($name)) {
				$className = $scope->getVarType($name);
			} else {
				$className = $node->var->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);
			}

			if ($node instanceof Expr\NullsafeMethodCall) {
				$className = TypeComparer::removeNullOption($className);
			}

			if (TypeComparer::ifAnyTypeIsNull($className)) {
				$this->emitError($fileName, $node, ErrorConstants::TYPE_NULL_METHOD_CALL, "Attempt to call $methodName() on a potentially null object");
				return;
			}

			TypeComparer::forEachType($className, function($classNameOb) use ($fileName, $methodName, $node, $scope, $inside, $className) {
				if ($classNameOb instanceof Node\IntersectionType) {
					// Only one interface of the intersection has to implement the method.
					// Multiple interfaces may require the same method
					$matchCount = 0;
					foreach ($classNameOb->types as $type) {
						if ($this->inspectIndividualName($type, $fileName, $node, $methodName, $scope, $inside)) {
							$matchCount++;
						}
					}
					if ($matchCount < 1) {
						$this->emitError($fileName, $node, ErrorConstants::TYPE_UNKNOWN_METHOD, "Call to unknown method of " . TypeComparer::typeToString($classNameOb) . "::$methodName");
					}
				} elseif ($classNameOb instanceof Node\Name) {
					if (!$this->inspectIndividualName($classNameOb, $fileName, $node, $methodName, $scope, $inside)) {
						$this->emitError($fileName, $node, ErrorConstants::TYPE_UNKNOWN_METHOD, "Call to unknown method of " . strval($classNameOb) . "::$methodName");
					}
				} else {
					if ($classNameOb != null && !TypeComparer::isNamedIdentifier($classNameOb, "mixed") && !TypeComparer::isNamedIdentifier($classNameOb, "object")) {
						$this->emitError($fileName, $node, ErrorConstants::TYPE_UNKNOWN_METHOD, "Methods can only be called on objects in call to $methodName not on " . TypeComparer::typeToString($className));
					}
					// We don't emit errors on mixed or unknown objects.
				}
			});
		}
	}

	/**
	 * checkMethod
	 *
	 * @param string          $fileName   The name of the file
	 * @param Node            $node       The node
	 * @param string          $className  The inside method
	 * @param string          $methodName The name of the method being checked
	 * @param ?Scope          $scope      Instance of Scope
	 * @param MethodInterface $method     Instance of MethodInterface
	 * @param ?ClassLike      $inside     What context we're executing inside (if any)
	 *
	 * @return void
	 */
	protected function checkMethod($fileName, $node, $className, $methodName, ?Scope $scope, MethodInterface $method, ?ClassLike $inside = null) {
		if ($method->isStatic()) {
			$this->emitError($fileName, $node, ErrorConstants::TYPE_INCORRECT_DYNAMIC_CALL, "Call to static method of $className::" . $method->getName() . " non-statically");
		}
		$callingFromClass = $inside ? strval($inside->namespacedName) : "";
		if ($method->getAccessLevel() == "private" && ($callingFromClass === "" || strcasecmp($className, $callingFromClass) != 0)) {
			$this->emitError($fileName, $node, ErrorConstants::TYPE_ACCESS_VIOLATION, "Attempt to call private method $className->" . $methodName);
		} elseif (
			$method->getAccessLevel() == "protected" &&
			(
				$callingFromClass === "" ||
				!$this->symbolTable->isParentClassOrInterface($className, $callingFromClass)
			)
		) {
			$this->emitError($fileName, $node, ErrorConstants::TYPE_ACCESS_VIOLATION, "Attempt to call protected method $className->" . $methodName);
		}

		$params = $method->getParameters();
		$minimumArgs = $method->getMinimumRequiredParameters();
		if (count($node->args) < $minimumArgs && !$node->isFirstClassCallable()) {
			$this->emitError($fileName, $node, ErrorConstants::TYPE_SIGNATURE_COUNT, "Function call parameter count mismatch to method " . $method->getName() . " (passed " . count($node->args) . " requires $minimumArgs)");
		}
		if (count($node->args) > count($params) && !$method->isVariadic()) {
			$this->emitError($fileName, $node, ErrorConstants::TYPE_SIGNATURE_COUNT_EXCESS, "Too many parameters to non-variadic method " . $method->getName() . " (passed " . count($node->args) . " only takes " . count($params) . ")");
		}
		if ($method->isDeprecated()) {
			$errorType = $method->isInternal() ? ErrorConstants::TYPE_DEPRECATED_INTERNAL : ErrorConstants::TYPE_DEPRECATED_USER;
			$this->emitError($fileName, $node, $errorType, "Call to deprecated function " . $method->getName());
		}

		$name = $className . "->" . $methodName;
		$templates["T"] = true;
		$this->checkParams($fileName, $node, $name, $scope, $node->args, $params, $templates);
	}

	/**
	 * Is the method being called preceded by a logical check to see if the method_exists()?
	 *
	 * @param Node $node
	 *
	 * @return bool
	 */
	private function wrappedByMethodExistsCheck(Expr\MethodCall|Expr\NullsafeMethodCall $node, ?Scope $scope = null): bool {
		if ($scope && $scope->getInsideFunction()) {
			$stmts = $scope->getInsideFunction()->getStmts();
			return $this->checkForMethodExists($node, $stmts);
		}

		return false;
	}

	/**
	 * Traverse each node tree to find a method_exists() function call for the requested method.
	 *
	 * @param Node  $node
	 * @param array $stmts
	 *
	 * @return bool
	 */
	private function checkForMethodExists(Expr\MethodCall|Expr\NullsafeMethodCall $node, array $stmts): bool {
		$match = false;
		ForEachNode::run($stmts, function($candidate) use (&$match, $node) {
			if (
				(
					$candidate instanceof Node\Stmt\If_ &&
					$this->isMatchingCond($candidate->cond, $candidate->stmts, $node)
				) || (
					$candidate instanceof Node\Expr\Ternary &&
					$this->isMatchingCond($candidate->cond, [$candidate->if], $node)
				)
			) {
				$match = true;
			}
		});
		return $match;
	}

	private function isMatchingCond(Expr $cond, array $trueNodes, Expr\MethodCall|Expr\NullsafeMethodCall $node): bool {
		$match = false;
		if ($cond instanceof Expr\FuncCall &&
			$cond->name instanceof Node\Name &&
			$cond->name->toString() == "method_exists" &&
			count($cond->args) >= 2 &&
			$cond->args[1]->value instanceof Node\Scalar\String_ &&
			$node->name instanceof Node\Identifier &&
			$cond->args[1]->value->value === $node->name->name
		) {
			ForEachNode::run($trueNodes, function($inner) use (&$match, $node) {
				if ($node === $inner) {
					$match = true;
				}
			});
		}
		return $match;
	}


	function inspectIndividualName(Node\Name $classNameOb, string $fileName, Expr\MethodCall|Expr\NullsafeMethodCall $node, string $methodName, Scope $scope, ?ClassLike $inside): bool {
		if ($classNameOb instanceof Node\Name &&
			$classNameOb == "T" &&
			$classNameOb->getAttribute('templates') &&
			$classNameOb->getAttribute('templates')[0]
		) {

			$classNameOb = $classNameOb->getAttribute('templates')[0];
		}

		$typeClassName = strval($classNameOb);
		$class = $this->symbolTable->getAbstractedClass($typeClassName);

		if (!$class) {
			$this->emitError($fileName, $node, ErrorConstants::TYPE_UNKNOWN_CLASS, "Unknown class $typeClassName in method call to $methodName()");
			return false;
		}
		//$templates= ["T"]; //$class->getTemplates()

		$method = Util::findAbstractedSignature($typeClassName, $methodName, $this->symbolTable);
		if ($method) {
			$this->checkMethod($fileName, $node, $method->getClass()->getName(), $methodName, $scope, $method, $inside);
			return true;
		} else {
			// If there is a magic __call method, then we can't know if it will handle these calls.
			if (
				!Util::findAbstractedMethod($typeClassName, "__call", $this->symbolTable) &&
				!$this->symbolTable->isParentClassOrInterface("iteratoriterator", $typeClassName) &&
				!$this->wrappedByMethodExistsCheck($node, $scope)
			) {

				return false;
			}
			return true;
		}
	}
}
