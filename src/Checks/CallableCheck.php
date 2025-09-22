<?php

namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeComparer;
use BambooHR\Guardrail\Util;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt\ClassLike;


/**
 * Class CallableCheck
 * @package BambooHR\Guardrail\Checks
 *
 * A callable node could be:
 *   - a string: call_user_func("foo");
 *   - a string name for a static call to a class method: call_user_func("\\SomeClass::method")
 *   - a static call array: call_user_func( ["Foo","bar"] );
 *   - a dynamic call array: call_user_func( [$this, "bar"] );
 *   - a closure function: call_user_func( function() { } );
 *
 * A check that expects to check type compatibility for a "callable" should forwards an expression
 * that it believes is a "callable" to this check.
 *
 */
class CallableCheck extends BaseCheck {

	/**
	 * CallableCheck constructor.
	 * @param SymbolTable     $symbolTable -
	 * @param OutputInterface $doc         -
	 */
	public function __construct(SymbolTable $symbolTable, OutputInterface $doc) {
		parent::__construct($symbolTable, $doc);
	}

	/**
	 *
	 * Callables don't have a node type.  This is a special check that we'll embed inside other checks for
	 * when they know they have a callable.
	 *
	 * @return array|string[]
	 */
	function getCheckNodeTypes() {
		return [];

	}

	/**
	 * @param string         $fileName      -
	 * @param Scope          $scope         -
	 * @param ClassLike|null $inside        -
	 * @param Expr\Array_    $callableArray -
	 * @return void
	 */
	protected function checkArrayCallable($fileName, Scope $scope, ?ClassLike $inside, Expr\Array_ $callableArray) {
		$itemCount = count($callableArray->items);
		if ($itemCount != 2) {
			$this->emitError($fileName, $callableArray, ErrorConstants::TYPE_SIGNATURE_TYPE, "Callable arrays must have two parameters, $itemCount detected");
			return;
		}
		$object = $callableArray->items[0]->value;
		if ($object instanceof Node\Scalar\String_) {
			// TODO: namespace resolution when resolving a callable string. Users should prefer [Foo::class,"Baz"] syntax.
			$this->checkClassType($object->value, $fileName, $callableArray);
		} elseif ($object instanceof Node\Expr\StaticPropertyFetch) {
			if ($object->class instanceof Node\Name && is_string($object->name) && $object->name == "class") {
				$this->checkClassType($object->name, $fileName, $callableArray);
			}
		} else {
			$classType = $object->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);
			TypeComparer::forEachType($classType,function($childClassType) use ($fileName, $callableArray) {
				if ($childClassType && ($childClassType instanceof Node\Identifier || $childClassType instanceof Node\Name)) {
					$this->checkClassType(strval($childClassType), $fileName, $callableArray);
				}
				;
			});
		}
	}

	/**
	 *
	 * @param string         $fileName -
	 * @param Node           $node     -
	 * @param ClassLike|null $inside   -
	 * @param Scope|null     $scope    -l
	 * @return void
	 */
	public function run($fileName, Node $node, ?ClassLike $inside = null, ?Scope $scope = null) {
		if ($node instanceof Node\Scalar\String_) {
			$funcName = $node->value;
			if ($funcName && $funcName[0] == "\\") {
				$funcName = substr($funcName, 1);
			}
			if (strpos($funcName, "::") !== false) {
				list($classType, $method) = explode('::', $funcName, 2);
				if (!$this->symbolTable->isDefinedClass($classType)) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_UNKNOWN_CALLABLE, "Callable string class '$classType' is not defined");
				} else {
					$methodObj = Util::findAbstractedMethod($classType, $method, $this->symbolTable);
					if (!$methodObj) {
						$this->emitError($fileName, $node, ErrorConstants::TYPE_UNKNOWN_CALLABLE, "Callable string method is '$classType::$method' is not defined");
					}
				}
			} else {
				$function = $this->symbolTable->getAbstractedFunction($funcName);
				if (!$function) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_UNKNOWN_CALLABLE, "Callable string '$funcName' is not a function name");
				}
			}
		} else if ($node instanceof Expr\Array_) {
			$this->checkArrayCallable($fileName, $scope, $inside, $node);
		}
	}

	/**
	 * @param mixed $classType
	 * @param string $fileName
	 * @param Expr\Array_ $callableArray
	 * @return void
	 */
	public function checkClassType(string $classType, string $fileName, Expr\Array_ $callableArray): void
	{
		if ($classType) {
			if (!$this->symbolTable->isDefinedClass($classType)) {
				$this->emitError($fileName, $callableArray, ErrorConstants::TYPE_UNKNOWN_CALLABLE, "Callable array class '$classType' is not defined");
			} else {
				$method = $callableArray->items[1]->value;
				if ($method instanceof Node\Scalar\String_) {
					$methodObj = Util::findAbstractedMethod($classType, $method->value, $this->symbolTable);
					if (!$methodObj) {
						$this->emitError($fileName, $callableArray, ErrorConstants::TYPE_UNKNOWN_CALLABLE, "Callable array method is '[$classType," . $method->value . "]' is not defined");
					}
				}
			}
		}
	}
}