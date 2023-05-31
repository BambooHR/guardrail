<?php

namespace BambooHR\Guardrail\Evaluators\Expression;

use BambooHR\Guardrail\Abstractions\MethodInterface;
use BambooHR\Guardrail\Config;
use BambooHR\Guardrail\Evaluators\ExpressionInterface;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\Scope\ScopeStack;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeComparer;
use MongoDB\BSON\Type;
use PhpParser\Node;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassLike;

class CallLike implements ExpressionInterface {
	function getInstanceType(): array|string
	{
		return [Node\Expr\CallLike::class, Node\Expr\Closure::class];
	}

	function onExit(Node $node, SymbolTable $table, ScopeStack $scopeStack): ?Node {
		/** @var Node\Expr\CallLike $call */
		$call = $node;

		if ($call instanceof Node\Expr\StaticCall)  {
			return $this->onStaticCall($call, $table, $scopeStack);
		} else if ($call instanceof Node\Expr\FuncCall) {
			return $this->onFunctionCall($call, $table, $scopeStack);
		} else if($call instanceof Node\Expr\New_) {
			return $this->onNew($call, $table, $scopeStack);
		} else if ($call instanceof Node\Expr\MethodCall || $call instanceof Node\Expr\NullsafeMethodCall) {
			return $this->onMethodCall($call, $table, $scopeStack);
		} else if ($call instanceof Node\Expr\Closure) {
			return TypeComparer::identifierFromName("Closure");
		}
		throw new \InvalidArgumentException("Unknown call type ".get_class($call));

		/*


			else if (gettype($call->name) == "string") {
					$params = $scopeStack->popTypes( count($expr->args) );
					$varType = $scopeStack->popTypes( 1 );
					TypeComparer::forEachType($varType, function($class) use ($expr,$scopeStack, $table):void {
						if ($class && !TypeComparer::isNamedIdentifier($class,"null")) {
							$classStr = Scope::constFromName($class);
							$type = $this->tryMakeCheck($expr, $classStr);
							if ($type) {
								//$scopeStack->pushTypes(Scope::nameFromName($type));
							}

							$method = $table->getAbstractedMethod($classStr, $expr->name);
							if ($method) {
								$scopeStack->pushTypes(
									$this->inferKnownMethod($method, $scopeStack->getCurrentClass(), $classStr)
								);
								return;
							}
						}
					});
					$scopeStack->pushTypes(null);
				}
		*/
	}


	function onNew(Node $node, SymbolTable $table, ScopeStack $scopeStack): ?Node
	{
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


	function onFunctionCall(Node\Expr\FuncCall $call, SymbolTable $table, ScopeStack $scopeStack):?Node {
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

			$this->checkForCastedCall($call, $scopeStack);

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

	function checkForCastedCall(Node\Expr\FuncCall $func, ScopeStack $scope) {
		if ($func->name instanceof Name &&
			count($func->args)==1 &&
			$func->args[0]->value instanceof Variable &&
			is_string($func->args[0]->value->name)
		) {
			$name=strtolower($func->name);
			$varName = $func->args[0]->value->name;
			switch($name) {
				case 'is_long':
				case 'is_integer':
				case 'is_int': $this->tagScopeAsType($func, $scope, $varName, "int"); break;

				// Asserting "object" is an upcast which ends up not being very informational.
				//case 'is_object': $this->tagScopeAsType($func, $scope, $varName, 'object'); break;

				case 'is_float':
				case 'is_double':
				case 'is_real': $this->tagScopeAsType($func, $scope, $varName, 'float'); break;

				case 'is_string': $this->tagScopeAsType($func, $scope, $varName, "string"); break;

				case 'is_null': $this->tagScopeAsType($func, $scope, $varName, "null"); break;
				case 'is_array': $this->tagScopeAsType($func, $scope, $varName, "array"); break;
			}
		}
	}

	function tagScopeAsType(Node $node, ScopeStack $parent, string $name, string $type) {
//		echo "Tag scope: $name=$type\n";
		$trueScope = $parent->getCurrentScope()->getScopeClone();
		$falseScope = $trueScope->getScopeClone();
		$trueScope->setVarType($name, TypeComparer::nameFromName($type), $node->getLine());
		$falseScope->setVarType($name,TypeComparer::removeNamedOption($falseScope->getVarType($name),$type), $node->getLine());
		$node->setAttribute('assertsTrue', $trueScope);
		$node->setAttribute('assertsFalse', $falseScope);
	}

	function onStaticCall(Node\Expr\StaticCall $call, SymbolTable $table, ScopeStack $scopeStack):?Node {
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

	static function mapReturnType(string $selfClass, ?Node $complexType):?Node {
		if (TypeComparer::isNamedIdentifier($complexType,"self") || TypeComparer::isNamedIdentifier($complexType, "static")) {
			return TypeComparer::nameFromName($selfClass);
		} else if($complexType instanceof Node\UnionType) {
			$types = [];
			TypeComparer::forEachType($complexType, function($type) use (&$types, $selfClass) {
				$types[] = (TypeComparer::isNamedIdentifier($type,"self") || TypeComparer::isNamedIdentifier($type,"static")) ? Scope::nameFromName($selfClass) : $type;
			});
			return TypeComparer::getUniqueTypes($types);
		}
		return $complexType;
	}

	function onMethodCall(Node\Expr\MethodCall|Node\Expr\NullsafeMethodCall $node, SymbolTable $table, ScopeStack $scopeStack):?Node {
		if ($node->name instanceof Node\Identifier) {
			$type = $node->var->getAttribute(TypeComparer::INFERRED_TYPE_ATTR);
			if ($type instanceof Node\Name || $type instanceof Node\Identifier) {
				$method = $table->getAbstractedMethod(strval($type), strval($node->name));
				if ($method) {
					$this->addReferenceParametersToLocalScope($scopeStack, $node->args, $method->getParameters());
					return self::mapReturnType(strval($type), $method->getComplexReturnType());
				}
			}
		}
		return null;
	}



	protected function tryMakeCheck(Node\Expr\MethodCall $expr, $class):string {

		// IoC
		if (
			strcasecmp($class, "Core\\App\\App") == 0 &&
			$expr->name == "make"
		) {
			if (count($expr->args) == 1) {
				$arg0 = $expr->args[0]->value;
				if ($arg0 instanceof Node\Expr\ClassConstFetch) {
					if (
						$arg0->class instanceof Name &&
						is_string($arg0->name) &&
						$arg0->name == "class"
					) {
						return strval($arg0->class);
					}
				}
			}
		}
		return "";
	}

	private function addReferenceParametersToLocalScope(Scope $scope, array $args, array $params):void {
		$paramCount = count($params);
		foreach ($args as $index => $arg) {
			if (
				(isset($params[$index]) && $params[$index]->isReference()) ||
				($index >= $paramCount && $paramCount > 0 && $params[$paramCount - 1]->isReference())
			) {
				$value = $arg->value;
				if ($value instanceof Variable) {
					if (gettype($value->name) == "string") {
						$scope->setVarType( $value->name, null, $value->getLine());
					}
				}
			}
		}
	}
}