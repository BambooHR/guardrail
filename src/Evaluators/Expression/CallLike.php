<?php

namespace BambooHR\Guardrail\Evaluators\Expression;

use BambooHR\Guardrail\Abstractions\FunctionLikeInterface;
use BambooHR\Guardrail\Config;
use BambooHR\Guardrail\Evaluators\ExpressionInterface;
use BambooHR\Guardrail\Evaluators\OnEnterEvaluatorInterface;
use BambooHR\Guardrail\NodePatterns;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeComparer;
use PhpParser\Node;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;

class CallLike implements ExpressionInterface, OnEnterEvaluatorInterface {
	function getInstanceType(): array | string {
		return [Node\Expr\CallLike::class, Node\Expr\Closure::class];
	}

	function onEnter(Node $node, SymbolTable $table, ScopeStack $scopeStack): void {
		// First pass registers &$parameters as a local value.
		// Second pass (for consistency with other evaluators) sets the return value of the function.
		$this->onExit($node, $table, $scopeStack, 1);
	}

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack, int $pass = 2): ?Node {
		/** @var Node\Expr\CallLike $call */
		$call = $node;

		// intval(...) syntax.  At runtime this evaluates to a callable.
		if ($call->isFirstClassCallable()) {
			return TypeComparer::identifierFromName("callable");
		}

		if ($call instanceof Node\Expr\StaticCall) {
			return $this->onStaticCall($call, $table, $scopeStack, $pass);
		} elseif ($call instanceof Node\Expr\FuncCall) {
			return $this->onFunctionCall($call, $table, $scopeStack, $pass);
		} elseif ($call instanceof Node\Expr\New_) {
			return $this->onNew($call, $table, $scopeStack, $pass);
		} elseif ($call instanceof Node\Expr\MethodCall || $call instanceof Node\Expr\NullsafeMethodCall) {
			return $this->onMethodCall($call, $table, $scopeStack, $pass);
		} elseif ($call instanceof Node\Expr\Closure) {
			return TypeComparer::identifierFromName("Closure");
		}
		throw new \InvalidArgumentException("Unknown call type " . get_class($call));
	}

	function onNew(Node $node, SymbolTable $table, ScopeStack $scopeStack, $pass): ?Node {
		/** @var Node\Expr\New_ $expr */
		$expr = $node;
		$inside = $scopeStack->getCurrentClass();

		if ($expr->class instanceof Name) {
			$className = $expr->class;

			if (strcasecmp($className, "self") == 0) {
				$className = $inside ? $inside->namespacedName : TypeComparer::identifierFromName("mixed");
			} else {
				if (strcasecmp($className, "static") == 0) {
					// Todo: track static scope to figure out which child class to invoke.
					$className = $inside ? $inside->namespacedName : TypeComparer::identifierFromName("mixed");
				}
			}

			return $className;
		} else {
			return null;
		}
	}

	function onFunctionCall(Node\Expr\FuncCall $call, SymbolTable $table, ScopeStack $scopeStack, $pass): ?Node {
		if (count($call->args) == 1 && $call->args[0] instanceof Node\VariadicPlaceholder) {
			return TypeComparer::identifierFromName("callable");
		}
		if ($call->name instanceof Node\Name) {
			if ($pass == 2) {
				if (strcasecmp($call->name, "assert") == 0 &&
					count($call->args) == 1
				) {
					$var = $call->args[0]->value;
					if ($var instanceof Instanceof_ &&
						$var->expr instanceof Variable &&
						is_string($var->expr->name) &&
						$var->class instanceof Node\Name
					) {
						$scopeStack->getCurrentScope()->setVarType($var->expr->name, $var->class, $var->getLine());
					}
				}
				$this->checkForVariableCastedCall($call, $scopeStack);
				$this->checkForPropertyCastedCall($call, $table, $scopeStack);

				// Special case for "get_defined_vars()".  Mark everything used.
				if (strcasecmp(strval($call->name), "get_defined_vars()") == 0) {
					$scopeStack->getCurrentScope()->markAllVarsUsed();
				}
			}

			$function = $table->getAbstractedFunction(strval($call->name));

			if ($function) {
				 if ($pass == 1) {
					 $this->addReferenceParametersToLocalScope($scopeStack, $call->args, $function->getParameters());
				 } else {
					 return $this->resolveReturnType($function, $call->args);
				 }
			}
		}

		return null;
	}

	function resolveReturnType(FunctionLikeInterface $function, array $args) {
		$docRet = $function->getDocBlockReturnType();
		if (
			Config::shouldUseDocBlockGenerics() &&
			$docRet instanceof Name &&
			strcasecmp($docRet, "T") == 0
		) {
			//echo "Returns ".$function->getName()."() returns T\n";
			$params = $function->getParameters();
			foreach ($params as $index => $param) {
			//	echo " Index: $index ".TypeComparer::typeToString($param->getType())."\n";
				if ($param->getType() instanceof Name) {
					$type = $param->getType();
					if (strcasecmp($type, "T") === 0) {
			//			echo "Return via param type T " . $type . "\n";
						return $args[$index]->value->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);
					}

					if (strcasecmp($type, "class-string") === 0) {
			//			echo "Looking for class-string at index $index\n";
						if (isset($args[$index]) && $args[$index]->value instanceof Node\Expr\ClassConstFetch) {
							$fetch = $args[$index]->value;
							if (
								TypeComparer::isNamedIdentifier($fetch->name, "class") &&
								$fetch->class instanceof Name
							) {
			//					echo "Return via class-string<" . $fetch->class . "> \n";
								return $fetch->class;
							}
						}
					}
				}
			}
			return null;
		}

		$type = $function->getComplexReturnType();
		if (!$type && Config::shouldUseDocBlockForReturnValues()) {
			return $function->getDocBlockReturnType();
		}
		return $type;
	}

	function checkForVariableCastedCall(Node\Expr\FuncCall $func, ScopeStack $scope) {
		if ($func->name instanceof Name &&
			count($func->args) == 1 &&
			$func->args[0]->value instanceof Variable &&
			is_string($func->args[0]->value->name)
		) {
			$varName = $func->args[0]->value->name;
			$type = $this->getCastedCallType(strtolower($func->name));
			if (!is_null($type) && !is_null($varName)) {
				$this->tagScopeAsType($func, $scope, $varName, $type);
			}
		}
	}

	function checkForPropertyCastedCall(Node\Expr\FuncCall $func, SymbolTable $table, ScopeStack $scopeStack) {
		if ($func->name instanceof Name &&
			count($func->args) == 1 &&
			(
				$func->args[0]->value instanceof Node\Expr\PropertyFetch ||
				$func->args[0]->value instanceof Node\Expr\NullsafePropertyFetch
			)
		) {
			$varName = NodePatterns::getVariableOrPropertyName($func->args[0]->value);
			$type = $this->getCastedCallType(strtolower($func->name));

			if (!is_null($type) && !is_null($varName)) {
				// The end node of the chain gets a specific type

				// If the last node in the chain is a specific type, then no node in the chain is null.
				if (
					$func->args[0]->value instanceof Node\Expr\PropertyFetch ||
					$func->args[0]->value instanceof Node\Expr\NullsafePropertyFetch
				) {
					$earlier = $func->args[0]->value;
					$this->tagScopeAsType($func, $scopeStack, $varName, $type);
					$falseScope = $func->getAttribute('assertsFalse');
					TypeComparer::removeNullInferences($earlier, $table, $falseScope, $earlier->getLine());
					$func->setAttribute('assertsFalse', $falseScope);
				}
			}
		}
	}

	private function getCastedCallType(string $functionName): ?string {
		return match ($functionName) {
			'is_long', 'is_integer', 'is_int', => 'int',
			'is_float', 'is_double', 'is_real' => 'float',
			'is_string' => 'string',
			'is_null' => 'null',
			'is_array' => 'array',
			default => null,
			// Asserting "object" is an upcast which ends up not being very informational.
			//case 'is_object': $this->tagScopeAsType($func, $scope, $varName, 'object'); break;
		};
	}

	function tagScopeAsType(Node $node, ScopeStack $parent, string $name, string $type) {
		$trueScope = $parent->getCurrentScope()->getScopeClone();
		$falseScope = $trueScope->getScopeClone();
		/** @var Node\Expr\CallLike $node */

		$trueScope->setVarType($name, TypeComparer::identifierFromName($type), $node->getLine());
		$falseVarType = $falseScope->getVarType($name) ?? $node->args[0]->value->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);
		$falseScope->setVarType($name, TypeComparer::removeNamedOption($falseVarType, $type), $node->getLine());
		$node->setAttribute('assertsTrue', $trueScope);
		$node->setAttribute('assertsFalse', $falseScope);
	}

	function onStaticCall(Node\Expr\StaticCall $call, SymbolTable $table, ScopeStack $scopeStack, $pass): ?Node {
		if (count($call->args) == 1 && $call->args[0] instanceof Node\VariadicPlaceholder) {
			return TypeComparer::identifierFromName("callable");
		}
		if ($call->class instanceof Node\Name && gettype($call->name) == "string") {
			$method = $table->getAbstractedMethod(strval($call->class), strval($call->name));
			if ($method) {
				if ($pass == 1) {
					$params = $method->getParameters();
					$this->addReferenceParametersToLocalScope($scopeStack, $call->getArgs(), $params);
				} else {
					$returnType = $this->resolveReturnType($method, $call->args);
					return self::mapReturnType($call->class, $returnType);
				}
			}
		}
		return null;
	}

	static function mapReturnType(Node $selfClass, ?Node $complexType): ?Node {
		if (TypeComparer::isNamedIdentifier($complexType, "self") || TypeComparer::isNamedIdentifier($complexType, "static")) {
			return $selfClass;
		} elseif ($complexType instanceof Node\UnionType) {
			$types = [];
			TypeComparer::forEachType($complexType, function ($type) use (&$types, $selfClass) {
				$types[] = (TypeComparer::isNamedIdentifier($type, "self") || TypeComparer::isNamedIdentifier($type, "static")) ? $selfClass : $type;
			});
			return TypeComparer::getUniqueTypes($types);
		}
		return $complexType;
	}

	function onMethodCall(Node\Expr\MethodCall | Node\Expr\NullsafeMethodCall $node, SymbolTable $table, ScopeStack $scopeStack, $pass): ?Node {
		if (count($node->args) == 1 && $node->args[0] instanceof Node\VariadicPlaceholder) {
			return TypeComparer::identifierFromName("callable");
		}
		if ($node->name instanceof Node\Identifier) {
			$type = $node->var->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);
			if (empty($type)) {
				if ($node->var instanceof New_) {
					$type = $this->onNew($node->var, $table, $scopeStack, 2);
				} elseif (!empty($node->var->name) && $node->var->name == "this") {
					$class = $scopeStack->getCurrentClass();
					$type = $class?->namespacedName ?: $class?->name;
				} elseif ($node->var instanceof Variable) {
					$type = $scopeStack->getCurrentScope()->getVarType($node->var->name);
				}
			}

			$types = ($type instanceof Node\UnionType ? $type->types : [$type]);

			$retTypes = [];
			foreach ($types as $type) {
				if ($type instanceof Node\IntersectionType) {
					// 1. Intersections are only meaningful for classes and interfaces.
					// 2. You can't simultaneously be an instanceof 2 classes unless on is the parent of the other.
					// 3. Therefore, determine which type is the class and use it to get the return value.
					$useType = null;
					foreach ($type->types as $iType) {
						if ($iType instanceof Name && $table->isDefinedClass(strval($iType))) {
							$useType = $iType;
						}
					}
					if (is_null($useType)) {
						$useType = $type->types[0];
					}
				} else {
					$useType = $type;
				}
				if ($useType instanceof Node\Name || $useType instanceof Node\Identifier) {
					$method = $table->getAbstractedMethod(strval($useType), strval($node->name));
					if ($method) {
						if ($pass == 1) {
							$this->addReferenceParametersToLocalScope($scopeStack, $node->args, $method->getParameters());
						} else {
							$returnType = $this->resolveReturnType($method, $node->args);
							$retTypes[] = self::mapReturnType($useType, $returnType);
						}
					}
				}
			}
			if ($pass == 2) {
				return TypeComparer::getUniqueTypes(...$retTypes);
			}
		}
		return null;
	}

	private function addReferenceParametersToLocalScope(Scope $scope, array $args, array $params): void {
		$paramCount = count($params);
		foreach ($args as $index => $arg) {
			if (
				(isset($params[$index]) && $params[$index]->isReference()) ||
				($index >= $paramCount && $paramCount > 0 && $params[$paramCount - 1]->isReference())
			) {
				$value = $arg->value;
				if ($value instanceof Variable) {
					if (gettype($value->name) == "string") {
						if ($scope->getVarExists($value->name)) {
							$scope->setVarUsed($value->name);
						}
						$value->setAttribute('assignment', true);
						$scope->setVarWritten(strval($value->name), $value->getLine());
						$scope->setVarType($value->name, null, $value->getLine());
					}
				}
			}
		}
	}
}
