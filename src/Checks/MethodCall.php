<?php namespace BambooHR\Guardrail\Checks;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Abstractions\FunctionLikeParameter;
use BambooHR\Guardrail\Abstractions\MethodInterface;
use BambooHR\Guardrail\Attributes;
use BambooHR\Guardrail\Metrics\Metric;
use BambooHR\Guardrail\Metrics\MetricOutputInterface;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Trait_;
use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeInferrer;
use BambooHR\Guardrail\Util;

/**
 * Class MethodCall
 *
 * @package BambooHR\Guardrail\Checks
 */
class MethodCall extends BaseCheck {

	/** @var TypeInferrer */
	private $inferenceEngine;

	/**
	 * @var CallableCheck
	 */
	private $callableCheck;

	/** @var MetricOutputInterface */
	protected $metricDoc;

	/**
	 * MethodCall constructor.
	 *
	 * @param SymbolTable     $symbolTable Instance of the SymbolTable
	 * @param OutputInterface $doc         Instance of OutputInterface
	 */
	public function __construct(SymbolTable $symbolTable, OutputInterface $doc, MetricOutputInterface $metricOutputInterface) {
		$this->metricDoc = $metricOutputInterface;
		parent::__construct($symbolTable, $doc);
		$this->inferenceEngine = new TypeInferrer($symbolTable);
		$this->callableCheck = new CallableCheck($symbolTable, $doc);
	}

	public function emitMetric($fileName, Node $node, $errorType, $metricData) {
		$lineNumber= $node->getLine();
		$metric = new Metric($fileName, $lineNumber, $errorType, $metricData);
		if ($trait = $node->getAttribute('importedByTrait')) {
			$metric->setCausedByTraitData($trait, $node->getAttribute('importedOnLine'));
		}
		$this->metricDoc->emitMetric($metric);
	}

	/**
	 * getCheckNodeTypes
	 *
	 * @return array
	 */
	public function getCheckNodeTypes() {
		return [\PhpParser\Node\Expr\MethodCall::class];
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
		static $checkable = 0, $uncheckable = 0;
		if ($node instanceof Expr\MethodCall) {
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
				list($className, $attributes) = $this->inferenceEngine->inferType($inside, $node->var, $scope);
			}
			if ($className  && $className != Scope::MIXED_TYPE && $attributes & Attributes::NULL_POSSIBLE) {
				$variable = ($node->var instanceof Node\Expr\Variable && is_string($node->var->name)) ? ' $' . $node->var->name : '';
				$this->emitError($fileName, $node, ErrorConstants::TYPE_NULL_DEREFERENCE, "Dereferencing potentially null object" . $variable);
			}
			if ($className != "" && $className[0] != "!") {
				if (!$this->symbolTable->isDefinedClass($className)) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_UNKNOWN_CLASS, "Unknown class $className in method call to $methodName()");
					return;
				}
				$method = Util::findAbstractedSignature($className, $methodName, $this->symbolTable);
				if ($method) {
					$this->checkMethod($fileName, $node, $className, $methodName, $scope, $method, $inside);
				} else {
					// If there is a magic __call method, then we can't know if it will handle these calls.
					if (
						!Util::findAbstractedMethod($className, "__call", $this->symbolTable) &&
						!$this->symbolTable->isParentClassOrInterface("iteratoriterator", $className)
					) {
						$this->emitError($fileName, $node, ErrorConstants::TYPE_UNKNOWN_METHOD, "Call to unknown method of $className::$methodName");
					}
				}
				$checkable ++;
			} else {
				$uncheckable++;
				//echo "Uncheckable method call $fileName ".$node->getLine()." ".$node->name." $checkable:$uncheckable\n";
			}
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
			return;
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
			$metricType = $method->isInternal() ? MetricConstants::TYPE_DEPRECATED_INTERNAL : MetricConstants::TYPE_DEPRECATED_USER;
			$this->emitMetric($fileName, $node, $metricType, ['class' => $className, 'method' => $methodName]);
		}

		$name = $className . "->" . $methodName;
		foreach ($node->args as $index => $arg) {
			$this->checkParam($fileName, $node, $name, $scope, $inside, $arg, $index, $params);
		}
		$calledClassParts = explode('\\', $className);
		if ($inside) {
			$callingClassParts = $inside->namespacedName->parts;
			for ($i = 0; $i < min(count($calledClassParts), count($callingClassParts)); $i++) {
				if ($callingClassParts[$i] !== $callingClassParts[$i]) {
					break;
				}
			}
			$sharedPrefixParts = $i-1;
			$this->emitMetric($fileName, $node, MetricConstants::TYPE_METHOD_CALL, ['callingClass' => implode('\\', $callingClassParts), 'calledClass' => $className, 'calledMethod' => $methodName, 'sharedNamespacePrefixParts' => $sharedPrefixParts]);
		}
	}

	/**
	 * @param string                  $fileName -
	 * @param Node                    $node     -
	 * @param string                  $name     -
	 * @param Scope                   $scope    -
	 * @param ClassLike               $inside   -
	 * @param Node\Arg                $arg      -
	 * @param int                     $index    -
	 * @param FunctionLikeParameter[] $params   -
	 * @return void
	 */
	protected function checkParam($fileName, $node, $name, Scope $scope, ClassLike $inside=null, $arg, $index, $params) {
		if ($scope && $arg->value instanceof Expr && $index < count($params)) {
			$variableName = $params[$index]->getName();
			list($type, $attributes) = $this->inferenceEngine->inferType($inside, $arg->value, $scope);
			if ($arg->unpack) {
				// Check if they called with ...$array.  If so, make sure $array is of type undefined or array
				$isSplatable = (
					substr($type, -2) == "[]" ||
					$type == "array" ||
					$type == Scope::ARRAY_TYPE ||
					$type == Scope::UNDEFINED ||
					$type == Scope::MIXED_TYPE ||
					$type == "" ||
					$this->symbolTable->isParentClassOrInterface(\Traversable::class, $type)
				);
				if (!$isSplatable) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_SIGNATURE_TYPE, "Splat (...) operator requires an array or traversable object.  Passing " . Scope::nameFromConst($type) . " from \$$variableName.");
				}
				return;// After we unpack an arg, we can't check the remaining parameters.
			} else {
				if ($params[$index]->getType() != "") {
					// Reference mismatch
					if ($params[$index]->isReference() && !($arg->value instanceof Variable || $arg->value instanceof Expr\ArrayDimFetch || $arg->value instanceof Expr\PropertyFetch)) {
						$this->emitError($fileName, $node, ErrorConstants::TYPE_SIGNATURE_TYPE, "Value passed to $name() parameter \$$variableName must be a reference type not an expression.");
					}

					// Type mismatch
					$expectedType = $params[$index]->getType();
					if (!in_array($type, [Scope::SCALAR_TYPE, Scope::MIXED_TYPE, Scope::UNDEFINED, Scope::STRING_TYPE, Scope::BOOL_TYPE, Scope::NULL_TYPE, Scope::INT_TYPE, Scope::FLOAT_TYPE]) &&
						$type != "" &&
						!$this->symbolTable->isParentClassOrInterface($expectedType, $type) &&
						!(strcasecmp($expectedType, "callable") == 0 && strcasecmp($type, "closure") == 0) &&
						!(strcasecmp($expectedType, "callable") == 0 && $type == Scope::ARRAY_TYPE) &&
						!(strcasecmp($expectedType, 'array') == 0 && (substr($type, -2) == "[]" || $type == Scope::ARRAY_TYPE))
					) {
						$this->emitError($fileName, $node, ErrorConstants::TYPE_SIGNATURE_TYPE, "Value passed to $name parameter \$$variableName must be a $expectedType, passing $type");
					}

					if (strcasecmp($expectedType, "callable") == 0) {
						$this->callableCheck->run($fileName, $arg->value, $inside, $scope);
					}

					// Nulls mismatch
					if (!$params[$index]->isNullable()) {
						if ($type == Scope::NULL_TYPE) {
							$this->emitError($fileName, $node, ErrorConstants::TYPE_SIGNATURE_TYPE, "NULL passed to $name parameter \$$variableName that does not accept nulls");
						} /*else if ($maybeNull == Scope::NULL_POSSIBLE) {
							$this->emitError($fileName, $node, ErrorConstants::TYPE_SIGNATURE_TYPE, "Potentially NULL value passed to $name parameter \$$variableName that does not accept nulls");
						}*/
					}
				}
			}
		}
	}
}
