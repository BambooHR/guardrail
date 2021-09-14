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
	protected CallableCheck $callableCheck;

	/**
	 * @var TypeInferrer
	 */
	protected TypeInferrer $inferenceEngine;

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
	protected function checkParam($fileName, $node, $name, Scope $scope, ClassLike $inside = null, $arg, $index, $params):void {
		if ($scope && $arg->value instanceof Node\Expr && $index < count($params)) {
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
					if ($params[$index]->isReference() &&
						!(
							$arg->value instanceof Variable ||
							$arg->value instanceof Expr\ArrayDimFetch ||
							$arg->value instanceof Expr\PropertyFetch
						)
					) {
						$this->emitError($fileName, $node, ErrorConstants::TYPE_SIGNATURE_TYPE, "Value passed to $name() parameter \$$variableName must be a reference type not an expression.");
					}

					// Type mismatch
					$expectedType = $params[$index]->getType();
					if ($expectedType instanceof UnionType) {
						$expectedTypes = $expectedType->types;
					} else {
						$expectedTypes = [$expectedType];
					}
					$this->verifyParamType($fileName, $node, $name, $variableName, $arg, $inside, $scope, $type, $expectedTypes);

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

	protected function verifyParamType($fileName, $node, $name, $variableName, $arg, $inside, $scope, $type, $expectedTypes):void {
		/*
		if (str_ends_with($fileName,"9.inc")) {
			echo $type;
			print_r($expectedTypes);
		}*/
		$passedAScalar = in_array($type, [Scope::SCALAR_TYPE, Scope::MIXED_TYPE, Scope::UNDEFINED, Scope::STRING_TYPE, Scope::BOOL_TYPE, Scope::NULL_TYPE, Scope::INT_TYPE, Scope::FLOAT_TYPE]);
		$passedTypeIsKnown = $type != '' && Scope::constFromName($type)!=Scope::MIXED_TYPE;
		if ($passedAScalar || !$passedTypeIsKnown) {
			return;
		}
		foreach ($expectedTypes as $expectedType) {
			if(strcasecmp($expectedType, "throwable") === 0 && (
					$this->symbolTable->isParentClassOrInterface("exception", $type) ||
					$this->symbolTable->isParentClassOrInterface("error", $type)
				)
			) {
				return;
			}

			if ($this->symbolTable->isParentClassOrInterface($expectedType, $type)) {
				return;
			}
			if (strcasecmp($expectedType, 'countable') === 0) {
				if (strcasecmp($type, 'array') === 0 || $type === Scope::ARRAY_TYPE || substr($type, -2) === '[]') {
					return;
				}
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