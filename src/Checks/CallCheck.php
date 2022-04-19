<?php namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Abstractions\FunctionLikeParameter;
use BambooHR\Guardrail\Checks\BaseCheck;
use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\TypeInferrer;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\UnionType;

abstract class CallCheck extends BaseCheck {
	/**
	 * @var CallableCheck
	 */
	protected $callableCheck;

	/**
	 * @var TypeInferrer
	 */
	protected $inferenceEngine;

	/**
	 * @param string                  $fileName
	 * @param Node                    $node
	 * @param string                  $name
	 * @param Scope                   $scope
	 * @param ClassLike|null          $inside
	 * @param Node\Arg[]              $args
	 * @param FunctionLikeParameter[] $params
	 * @return void
	 */
	protected function checkParams($fileName, $node, $name, Scope $scope, ClassLike $inside = null, array $args, array $params) {
		$named = false;
		$covered = array_fill(0, count($params), 0);
		foreach($args as $index=>$arg) {
			if ($arg->name === null) {
				if(!$named) {
					$covered[$index] = 1;
					if($index<count($params)) {
						$this->checkParam($fileName, $node, $name, $scope, $inside, $arg, $params[$index]);
					}
				} else {
					$this->emitError($fileName, $arg, ErrorConstants::TYPE_SIGNATURE_TYPE,"Attempt to pass positional param after named param");
				}
			} else {
				$named = true;
				$index2=self::findParam($params,$arg->name->name);
				if($index2>=0) {
					if ($covered[$index2]) {
						$this->emitError($fileName, $arg, ErrorConstants::TYPE_SIGNATURE_TYPE, "Attempt to pass param \"" . $arg->name->name . "\" twice");
					} else {
						$covered[$index2]=1;
						$this->checkParam($fileName, $node, $name, $scope, $inside, $arg, $params[$index2]);
					}
				}
			}
		}
		foreach($params as $index=>$param) {
			if(!$covered[$index] && !$param->isOptional()) {
				$this->emitError($fileName, $node,ErrorConstants::TYPE_SIGNATURE_TYPE, "$name: required parameter ".$param->getName()." was not passed");
			}
		}
	}

	/**
	 * @param array  $params
	 * @param string $name
	 * @return int
	 */
	static function findParam(array $params, string $name):int {
		foreach($params as $index=>$param) {
			if (strcasecmp($param->getName(), $name)==0) {
				return $index;
			}
		}
		return -1;
	}

	/**
	 * @param string                $fileName -
	 * @param Node                  $node     -
	 * @param string                $name     -
	 * @param Scope                 $scope    -
	 * @param ClassLike             $inside   -
	 * @param Node\Arg              $arg      -
	 * @param FunctionLikeParameter $param    -
	 * @return void
	 */
	protected function checkParam($fileName, $node, $name, Scope $scope, ClassLike $inside = null, Node\Arg $arg, FunctionLikeParameter $param) {
		$variableName = $param->getName();
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
			if ($param->getType() != "") {
				// Reference mismatch
				if ($param->isReference() &&
					!(
						$arg->value instanceof Variable ||
						$arg->value instanceof Expr\ArrayDimFetch ||
						$arg->value instanceof Expr\PropertyFetch
					)
				) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_SIGNATURE_TYPE, "Value passed to $name() parameter \$$variableName must be a reference type not an expression.");
				}

				// Type mismatch
				$expectedType = $param->getType();
				if ($expectedType instanceof UnionType) {
					$expectedTypes = $expectedType->types;
				} else {
					$expectedTypes = [$expectedType];
				}
				$this->verifyParamType($fileName, $node, $name, $variableName, $arg, $inside, $scope, $type, $expectedTypes);

				// Nulls mismatch
				if (!$param->isNullable()) {
					if ($type == Scope::NULL_TYPE) {
						$this->emitError($fileName, $node, ErrorConstants::TYPE_SIGNATURE_TYPE, "NULL passed to $name parameter \$$variableName that does not accept nulls");
					} /*else if ($maybeNull == Scope::NULL_POSSIBLE) {
						$this->emitError($fileName, $node, ErrorConstants::TYPE_SIGNATURE_TYPE, "Potentially NULL value passed to $name parameter \$$variableName that does not accept nulls");
					}*/
				}
			}
		}
	}

	protected function verifyParamType($fileName, $node, $name, $variableName, $arg, $inside, $scope, $type, $expectedTypes) {
		$passedAScalar = in_array($type, [Scope::SCALAR_TYPE, Scope::MIXED_TYPE, Scope::UNDEFINED, Scope::STRING_TYPE, Scope::BOOL_TYPE, Scope::NULL_TYPE, Scope::INT_TYPE, Scope::FLOAT_TYPE]);
		$passedTypeIsKnown = $type != '';
		if ($passedAScalar || !$passedTypeIsKnown) {
			return;
		}
		foreach ($expectedTypes as $expectedType) {
			if (strcasecmp($expectedType, 'countable') == 0 && ($type == Scope::ARRAY_TYPE || substr($type, -2) == "[]")) {
				return;
			}
			if ($this->symbolTable->isParentClassOrInterface($expectedType, $type)) {
				return;
			}
			if ((strcasecmp($expectedType, "callable") == 0 && strcasecmp($type, "closure") == 0) ||
				(strcasecmp($expectedType, "callable") == 0 && $type == Scope::ARRAY_TYPE) ||
				(strcasecmp($expectedType, 'array') == 0 && (substr($type, -2) == "[]" || $type == Scope::ARRAY_TYPE))
			) {
				if (strcasecmp($expectedType, "callable") == 0) {
					$this->callableCheck->run($fileName, $arg->value, $inside, $scope);
				}
				return;
			}
		}
		$this->emitError($fileName, $node, ErrorConstants::TYPE_SIGNATURE_TYPE, "Value passed to $name parameter \$$variableName must be a $expectedType, passing $type");
	}
}