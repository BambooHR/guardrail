<?php namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Abstractions\FunctionLikeParameter;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\TypeComparer;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\ClassLike;

abstract class CallCheck extends BaseCheck {
	/**
	 * @var CallableCheck
	 */
	protected $callableCheck;


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
				} else {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_SIGNATURE_TYPE, "Unable to find named parameter ".$arg->name->name);
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
		//TODO: There is a problem with this line getting back the wrong type.. (see TestComplexTypes.php:36)
		$type = $arg->value->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);
		if ($arg->unpack) {
			$tc=new TypeComparer($this->symbolTable);
			if (!$tc->isTraversable($type)) {
				$this->emitError($fileName, $node, ErrorConstants::TYPE_SIGNATURE_TYPE, "Splat (...) operator requires an array or traversable object.  Passing " . TypeComparer::typeToString($type) . " from \$$variableName.");
			}
			return;// After we unpack an arg, we can't check the remaining parameters.
		} else {
			$expectedType = $param->getType();
			if($expectedType && $param->isNullable()) {
				$expectedType = TypeComparer::getUniqueTypes($expectedType, TypeComparer::identifierFromName("null"));
			}
			if ($expectedType) {
				// Reference mismatch
				if ($param->isReference() &&
					!(
						$arg->value instanceof Variable ||
						$arg->value instanceof Expr\ArrayDimFetch ||
						$arg->value instanceof Expr\PropertyFetch ||
						$arg->value instanceof Expr\StaticPropertyFetch
					)
				) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_SIGNATURE_TYPE, "Value passed to $name() parameter \$$variableName must be a reference type not an expression.");
				}

				// Type mismatch
				$checker = new TypeComparer($this->symbolTable);

				if ($type && !$checker->isCompatibleWithTarget($expectedType, $type, $scope)) {
					$nullOnlyError = false;
					$typeStr=TypeComparer::typeToString($type);
					if ($type instanceof Node\UnionType || $type instanceof Node\NullableType || TypeComparer::isNamedIdentifier($type,"null")) {
						$typeWithOutNull = TypeComparer::removeNullOption($type);
						$nullOnlyError = $checker->isCompatibleWithTarget($expectedType, $typeWithOutNull, $scope);
					}
					$this->emitError($fileName, $node,
						$nullOnlyError ? ErrorConstants::TYPE_SIGNATURE_TYPE_NULL : ErrorConstants::TYPE_SIGNATURE_TYPE,
						"Incompatible type passed to $name parameter \$$variableName ".
						"expected ".TypeComparer::typeToString($expectedType). ", passed $typeStr ".($scope->isStrict() ? "STRICT" : "")
					);
				}
			}
		}
	}
}