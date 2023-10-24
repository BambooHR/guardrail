<?php namespace BambooHR\Guardrail\Checks;

/**
 * Guardrail.  Copyright (c) 2016-2023, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Abstractions\MethodInterface;
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
	 * @return mixed
	 */
	public function run($fileName, Node $node, ClassLike $inside=null, Scope $scope=null) {

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

			$className = "";
			$var = $node->var;
			if ($var instanceof Variable) {
				if ($var->name == "this" && !$inside) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_SCOPE_ERROR, "Can't use \$this outside of a class");
					return;
				}
			}
			if ($scope) {
				$className = $node->var->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);
			}

			if($node instanceof Expr\NullsafeMethodCall) {
				$className = TypeComparer::removeNullOption($className);
			}

			TypeComparer::forEachType($className, function($classNameOb) use ($fileName, $methodName, $node, $scope, $inside, $className) {
				$isNull = TypeComparer::isNamedIdentifier($classNameOb,"null");
				if($classNameOb instanceof Node\Name || $isNull) {
					if ($isNull) {
						$this->emitError($fileName, $node, ErrorConstants::TYPE_NULL_METHOD_CALL, "Attempt to call $methodName() on a potentially null object");
						return;
					} else {
						$typeClassName = strval($classNameOb);
						if (!$this->symbolTable->isDefinedClass($typeClassName)) {
							$this->emitError($fileName, $node, ErrorConstants::TYPE_UNKNOWN_CLASS, "Unknown class $typeClassName in method call to $methodName()");
							return;
						}
					}
					$method = Util::findAbstractedSignature($typeClassName, $methodName, $this->symbolTable);
					if ($method) {
						$this->checkMethod($fileName, $node, $typeClassName, $methodName, $scope, $method, $inside);
					} else {
						// If there is a magic __call method, then we can't know if it will handle these calls.
						if (
							!Util::findAbstractedMethod($typeClassName, "__call", $this->symbolTable) &&
							!$this->symbolTable->isParentClassOrInterface("iteratoriterator", $typeClassName) &&
							!$this->wrappedByMethodExistsCheck($node, $scope)
						) {
							$this->emitError($fileName, $node, ErrorConstants::TYPE_UNKNOWN_METHOD, "Call to unknown method of $typeClassName::$methodName");
						}
					}
				} else {
					if ($classNameOb != null && !TypeComparer::isNamedIdentifier($classNameOb,"mixed") && !TypeComparer::isNamedIdentifier($classNameOb,"object")) {
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
	 * @param Scope           $scope      Instance of Scope
	 * @param MethodInterface $method     Instance of MethodInterface
	 * @param ClassLike       $inside     What context we're executing inside (if any)
	 *
	 * @return void
	 */
	protected function checkMethod($fileName, $node, $className, $methodName, Scope $scope, MethodInterface $method, ClassLike $inside=null) {
		if ($method->isStatic()) {
			$this->emitError($fileName, $node, ErrorConstants::TYPE_INCORRECT_DYNAMIC_CALL, "Call to static method of $className::" . $method->getName() . " non-statically");
		}

		if ($method->getAccessLevel() == "private" && (!$inside || !isset($inside->namespacedName) || strcasecmp($className, $inside->namespacedName) != 0)) {
			$this->emitError($fileName, $node, ErrorConstants::TYPE_ACCESS_VIOLATION, "Attempt call private method " . $methodName);
		} else if ($method->getAccessLevel() == "protected" && (!$inside || !isset($inside->namespacedName) || !$this->symbolTable->isParentClassOrInterface($className, $inside->namespacedName))) {
			$this->emitError($fileName, $node, ErrorConstants::TYPE_ACCESS_VIOLATION, "Attempt to call protected method " . $methodName);
		}

		$params = $method->getParameters();
		$minimumArgs = $method->getMinimumRequiredParameters();
		if (count($node->args) < $minimumArgs) {
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
		$this->checkParams($fileName, $node, $name, $scope, $inside, $node->args, $params);
	}

	/**
	 * Is the method being called preceded by a logical check to see if the method_exists()?
	 *
	 * @param Node $node
	 *
	 * @return bool
	 */
	private function wrappedByMethodExistsCheck(Expr\MethodCall $node, Scope $scope = null): bool {
		if ($scope && $scope->getInsideFunction()) {
			$stmts = $scope->getInsideFunction()->getStmts();
			return $this->checkForMethodExists($node, $stmts);
		}

		return false;
	}

	/**
	 * Traverse each node tree to find a method_exists() function call for the requested method.
	 *
	 * @param Node $node
	 * @param Node $stmt
	 *
	 * @return bool
	 */
	private function checkForMethodExists(Expr\MethodCall $node, array $stmts): bool {
		$match = false;
		ForEachNode::run( $stmts, function($candidate) use (&$match, $node) {
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

	private function isMatchingCond(Expr $cond, array $trueNodes, Expr\MethodCall $node):bool {
		$match = false;
		if ($cond instanceof Expr\FuncCall &&
			$cond->name instanceof Node\Name &&
			$cond->name->toString()=="method_exists" &&
			count($cond->args) >= 2 &&
			$cond->args[1]->value instanceof Node\Scalar\String_ &&
			$node->name instanceof Node\Identifier &&
			$cond->args[1]->value->value === $node->name->name
		) {
			ForEachNode::run( $trueNodes, function($inner) use (&$match, $node) {
				if ($node===$inner) {
					$match = true;
				}
			});
		}
		return $match;
	}
}
