<?php

namespace BambooHR\Guardrail\Evaluators\Expression;

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

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): ?Node {
		/** @var Node\Expr\CallLike $call */
		$call = $node;

		if ($call instanceof Node\Expr\StaticCall) {
			return $this->onStaticCall($call, $table, $scopeStack);
		} else if ($call instanceof Node\Expr\FuncCall) {
			return $this->onFunctionCall($call, $table, $scopeStack);
		} else if ($call instanceof Node\Expr\New_) {
			return $this->onNew($call, $table, $scopeStack);
		} else if ($call instanceof Node\Expr\MethodCall || $call instanceof Node\Expr\NullsafeMethodCall) {
			return $this->onMethodCall($call, $table, $scopeStack);
		} else if ($call instanceof Node\Expr\Closure) {
			return TypeComparer::identifierFromName("Closure");
		}
		throw new \InvalidArgumentException("Unknown call type " . get_class($call));
	}

	function onEnter(Node $node, SymbolTable $table, ScopeStack $scopeStack): void {
		$this->onExit($node, $table, $scopeStack);
	}

	function onNew(Node $node, SymbolTable $table, ScopeStack $scopeStack): ?Node {
		/** @var Node\Expr\New_ $expr */
		$expr = $node;
		$inside = $scopeStack->getCurrentClass();

		if ($expr->class instanceof Name) {

			$className = strval($expr->class);

			if (strcasecmp($className, "self") == 0) {
				$className = $inside ? strval($inside->namespacedName) : TypeComparer::identifierFromName("mixed");
			} else {
				if (strcasecmp($className, "static") == 0) {
					// Todo: track static scope to figure out which child class to invoke.
					$className = $inside ? strval($inside->namespacedName) : TypeComparer::identifierFromName("mixed");
				}
			}

			return TypeComparer::nameFromName($className);
		} else {
			return null;
		}
	}

	function onFunctionCall(Node\Expr\FuncCall $call, SymbolTable $table, ScopeStack $scopeStack): ?Node {
		if ($call->name instanceof Node\Name) {
			if (strcasecmp($call->name, "assert") == 0 &&
				count($call->args) == 1
			) {
				$var = $call->args[0]->value;
				if ($var instanceof Instanceof_) {
					$expr = $var->expr;
					if ($expr instanceof Variable) {
						$class = $var->class;
						if ($class instanceof Node\Name) {
							if (gettype($expr->name) == "string") {
								$scopeStack->getCurrentScope()->setVarType($expr->name, TypeComparer::nameFromName(strval($class)), $var->getLine());
							}
						}
					}
				}
			}

			$this->checkForVariableCastedCall($call, $scopeStack);
			$this->checkForPropertyCastedCall($call, $scopeStack);

			// Special case for "get_defined_vars()".  Mark everything used.
			if (strcasecmp(strval($call->name), "get_defined_vars()") == 0) {
				$scopeStack->getCurrentScope()->markAllVarsUsed();
			}

			$function = $table->getAbstractedFunction(strval($call->name));
			if ($function) {
				$this->addReferenceParametersToLocalScope($scopeStack, $call->args, $function->getParameters());

				return $function->getComplexReturnType();
			}
		}

		return null;
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

	function checkForPropertyCastedCall(Node\Expr\FuncCall $func, ScopeStack $scope) {
		if ($func->name instanceof Name &&
			count($func->args) == 1 &&
			$func->args[0]->value instanceof Node\Expr\PropertyFetch
		) {
			$varName = NodePatterns::getVariableOrPropertyName($func->args[0]->value);
			$type = $this->getCastedCallType(strtolower($func->name));
			if (!is_null($type) && !is_null($varName)) {
				$this->tagScopeAsType($func, $scope, $varName,$type);
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
		$trueScope->setVarType($name, TypeComparer::nameFromName($type), $node->getLine());
		$falseScope->setVarType($name, TypeComparer::removeNamedOption($falseScope->getVarType($name), $type), $node->getLine());
		$node->setAttribute('assertsTrue', $trueScope);
		$node->setAttribute('assertsFalse', $falseScope);
	}

	function onStaticCall(Node\Expr\StaticCall $call, SymbolTable $table, ScopeStack $scopeStack): ?Node {
		if ($call->class instanceof Node\Name && gettype($call->name) == "string") {
			$method = $table->getAbstractedMethod(strval($call->class), strval($call->name));
			if ($method) {
				$params = $method->getParameters();
				$this->addReferenceParametersToLocalScope($scopeStack, $call->getArgs(), $params);

				return self::mapReturnType(strval($call->class), $method->getComplexReturnType());
			}
		}

		return null;
	}

	static function mapReturnType(string $selfClass, ?Node $complexType): ?Node {
		if (TypeComparer::isNamedIdentifier($complexType, "self") || TypeComparer::isNamedIdentifier($complexType, "static")) {
			return TypeComparer::nameFromName($selfClass);
		} else if ($complexType instanceof Node\UnionType) {
			$types = [];
			TypeComparer::forEachType($complexType, function ($type) use (&$types, $selfClass) {
				$types[] = (TypeComparer::isNamedIdentifier($type, "self") || TypeComparer::isNamedIdentifier($type, "static")) ? Scope::nameFromName($selfClass) : $type;
			});

			return TypeComparer::getUniqueTypes($types);
		}

		return $complexType;
	}

	function onMethodCall(Node\Expr\MethodCall | Node\Expr\NullsafeMethodCall $node, SymbolTable $table, ScopeStack $scopeStack): ?Node {
		if ($node->name instanceof Node\Identifier) {
			$type = $node->var->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);
			if (empty($type)) {
				if ($node->var instanceof New_) {
					$type = $this->onNew($node->var, $table, $scopeStack);
				} else if (!empty($node->var->name) && $node->var->name == "this") {
					$class = $scopeStack->getCurrentClass();
					$name = strval($class?->namespacedName ?: $class?->name ?? null);
					$type = ($name ? TypeComparer::nameFromName($name) : null);
				}
				else if ($node->var instanceof Variable) {
					$type = $scopeStack->getCurrentScope()->getVarType($node->var->name);
				}
			}
			$types = [$type];
			if ($type instanceof Node\UnionType) {
				$types = [];
				foreach ($type->types as $type) {
					$types[] = $type;
				}
			}
			foreach ($types as $type) {
				if ($type instanceof Node\Name || $type instanceof Node\Identifier) {
					$method = $table->getAbstractedMethod(strval($type), strval($node->name));
					if ($method) {
						$this->addReferenceParametersToLocalScope($scopeStack, $node->args, $method->getParameters());

						return self::mapReturnType(strval($type), $method->getComplexReturnType());
					}
				}
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