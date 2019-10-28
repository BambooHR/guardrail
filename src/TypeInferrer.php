<?php namespace BambooHR\Guardrail;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Abstractions\MethodInterface;
use BambooHR\Guardrail\Checks\DefinedConstantCheck;
use BambooHR\Guardrail\Checks\MethodCall;
use PhpParser\Node;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\Clone_;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt\ClassLike;
use Prophecy\Argument\Token\LogicalNotToken;

/**
 * Class TypeInferrer
 *
 * @package BambooHR\Guardrail
 */
class TypeInferrer {

	/** @var SymbolTable */
	private $index;

	/**
	 * TypeInferrer constructor.
	 *
	 * @param SymbolTable $table Instance of SymbolTable
	 */
	public function __construct(SymbolTable $table) {
		$this->index = $table;
	}

	/**
	 * inferType
	 *
	 * Do some simplistic checks to see if we can figure out object type.  If we can, then we can check method calls
	 * using that variable for correctness.
	 *
	 * @param ClassLike|null $inside Instance of ClassLike
	 * @param Expr|null      $expr   Instance of Expr
	 * @param Scope          $scope  Instance of Scope
	 *
	 * @return array [0]=Type [1]=maybe null [2]=attributes
	 * @todo This looks like a good place for a strategy pattern
	 */
	public function inferType(ClassLike $inside = null, Expr $expr = null, Scope $scope) {
		if ($expr instanceof Expr\Cast\Int_) {
			return [Scope::INT_TYPE, 0];
		}
		if ($expr instanceof Expr\Cast\Double) {
			return [Scope::FLOAT_TYPE, 0];
		}
		if ($expr instanceof Expr\ArrowFunction) {
			return ['Closure', 0];
		}
		if ($expr instanceof Expr\Cast\String_) {
			list(, $attributes) = $this->inferType($inside, $expr->expr, $scope);
			return [Scope::STRING_TYPE, $attributes & ~Attributes::NULL_POSSIBLE];
		}
		if ($expr instanceof Expr\Cast\Bool_) {
			return [Scope::BOOL_TYPE, 0];
		}
		if ($expr instanceof Expr\Instanceof_) {
			list(, $attributes) = $this->inferType($inside, $expr->expr, $scope);
			return [Scope::BOOL_TYPE, $attributes & ~Attributes::NULL_POSSIBLE];
		}
		if ($expr instanceof AssignOp) {
			return $this->inferType($inside, $expr->expr, $scope);
		}
		if ($expr instanceof Node\Expr\Array_) {
			return [Scope::ARRAY_TYPE, 0];
		}
		if ($expr instanceof Scalar) {
			$attributes = 0;
			$type = Scope::SCALAR_TYPE;
			if ($expr instanceof Scalar\LNumber) {
				$type = Scope::INT_TYPE;
				$attributes = Attributes::IS_CONST;
			} else if ($expr instanceof Scalar\DNumber) {
				$type = Scope::FLOAT_TYPE;
				$attributes = Attributes::IS_CONST;
			} else if ($expr instanceof Scalar\String_) {
				$type = Scope::STRING_TYPE;
				$attributes = Attributes::IS_CONST;
			} else if ($expr instanceof Scalar\Encapsed) {
				$type = Scope::STRING_TYPE;
			} else if ($expr instanceof Scalar\MagicConst) {
				$type = Scope::STRING_TYPE;
				$attributes = Attributes::IS_CONST;
			}
			return [$type, $attributes];
		}
		if ($expr instanceof New_) {
			if ($expr->class instanceof Name) {
				$className = strval($expr->class);
				if (strcasecmp($className, "self") == 0) {
					$className = $inside ? strval($inside->namespacedName) : Scope::MIXED_TYPE;
				} else {
					if (strcasecmp($className, "static") == 0) {
						// Todo: track static scope to figure out which child class to invoke.
						$className = $inside ? strval($inside->namespacedName) : Scope::MIXED_TYPE;
					}
				}
				return [$className, 0];
			}
			return [Scope::MIXED_TYPE, 0];
		}
		if ($expr instanceof Node\Expr\Variable) {
			if (gettype($expr->name) == "string") {

				$varName = strval($expr->name);
				if ($varName == "this" && $inside && isset($inside->namespacedName)) {
					return [strval($inside->namespacedName), 0];
				}
				if ($varName == "_GET" || $varName == "_POST" || $varName == "_COOKIE" || $varName == "_REQUEST") {
					return [Scope::STRING_TYPE, Attributes::TOUCHED_USER_INPUT];
				}
				$scopeType = $scope->getVarType($varName);
				$attributes = $scope->getVarAttributes($varName);
				if ($scopeType != Scope::UNDEFINED) {
					return [$scopeType, $scope->getVarNullability($varName), $attributes];
				}
				return [Scope::MIXED_TYPE, Attributes::NULL_POSSIBLE];
			}
		}
		if ($expr instanceof Closure) {
			return ["Closure", 0];
		}
		if ($expr instanceof FuncCall) {
			return $this->inferFunctionCall($inside, $expr, $scope);
		}

		if ($expr instanceof Expr\StaticCall) {
			return $this->inferStaticCall($inside, $expr);
		}
		if ($expr instanceof Node\Expr\MethodCall) {
			return $this->inferMethodCall($inside, $expr, $scope);
		}
		if ($expr instanceof PropertyFetch) {
			return $this->inferPropertyFetch($expr, $inside, $scope);
		}
		if ($expr instanceof ArrayDimFetch) {
			list($type, $attributes) = $this->inferType($inside, $expr->var, $scope);
			if (substr($type, -2) == "[]") {
				return [Scope::constFromDocBlock(substr($type, 0, -2)), $attributes & ~(Attributes::NULL_POSSIBLE)];
			} else {
				return [Scope::MIXED_TYPE, $attributes];
			}
		}
		if ($expr instanceof Clone_) {
			// A cloned node will be the same type as whatever we're cloning.
			return $this->inferType($inside, $expr->expr, $scope);
		}
		if ($expr instanceof Expr\ConstFetch) {
			if (strcasecmp($expr->name, "null") == 0) {
				return [Scope::NULL_TYPE, Attributes::IS_CONST | Attributes::NULL_POSSIBLE];
			}
			if (strcasecmp($expr->name, "false") == 0 || strcasecmp($expr->name, "true") == 0) {
				return [Scope::BOOL_TYPE, Attributes::IS_CONST];
			}
			if (defined($expr->name)) {
				// Guardrail doesn't declare any global constants.  Any that exist are from the runtime.
				return [Scope::MIXED_TYPE, Attributes::IS_CONST];
			}
			if ($this->index->isDefined($expr->name)) {
				return [Scope::MIXED_TYPE, Attributes::IS_CONST];
			}
			return [Scope::UNDEFINED, Attributes::NULL_POSSIBLE];
		}
		if ($expr instanceof Expr\Ternary) {
			list($type1, $attr1) = $this->inferType($inside, $expr->if, $scope);
			list($type2, $attr2) = $this->inferType($inside, $expr->else, $scope);
			return [
				$type1 == $type2 ? $type1 : Scope::MIXED_TYPE,
				(Attributes::combine($attr1, $attr2) & ~Attributes::NULL_POSSIBLE) | (($attr1 || $attr2) & Attributes::NULL_POSSIBLE)
			];
		}
		if ($expr instanceof Node\Expr\BinaryOp) {
			return $this->inferBinaryOp($expr, $inside, $scope);
		}

		return [Scope::MIXED_TYPE, Scope::NULL_UNKNOWN, 0];
	}


	/**
	 * @param Expr\BinaryOp  $expr   -
	 * @param ClassLike|null $inside -
	 * @param Scope          $scope  -
	 * @return array
	 */
	function inferBinaryOp(Node\Expr\BinaryOp $expr, ClassLike $inside=null, Scope $scope) {
		list($type1, $attr1) = $this->inferType($inside, $expr->left, $scope);
		list($type2, $attr2) = $this->inferType($inside, $expr->right, $scope);

		if ($expr instanceof Expr\BinaryOp\Spaceship) {
			return [Scope::INT_TYPE, Attributes::combine($attr1, $attr2) & ~Attributes::NULL_POSSIBLE];
		}
		if ($expr instanceof Expr\BinaryOp\Coalesce) {
			return [
				$type1 == $type2 ? $type1 : Scope::MIXED_TYPE,
				($attr2 & Attributes::NULL_POSSIBLE) | (~Attributes::NULL_POSSIBLE & Attributes::combine($attr1, $attr2))
			];
		}
		if ($expr instanceof Expr\BinaryOp\Concat) {
			return [Scope::STRING_TYPE, Scope::NULL_IMPOSSIBLE & ~Attributes::combine($attr1, $attr2)];
		}
		if ($expr instanceof Expr\BinaryOp\Minus ||
			$expr instanceof Expr\BinaryOp\Plus ||
			$expr instanceof Expr\BinaryOp\Mul
		) {
			$attr = Attributes::combine($attr1, $attr2) & ~Attributes::NULL_POSSIBLE;
			if ($type1 == $type2 && $type1 == Scope::INT_TYPE) {
				return [Scope::INT_TYPE, $attr];
			}
			if ($type1 == $type2 && $type1 == Scope::FLOAT_TYPE) {
				return [Scope::FLOAT_TYPE, $attr];
			}
			return [Scope::MIXED_TYPE, $attr];
		}
		return [Scope::MIXED_TYPE, Attributes::combine($attr1, $attr2)];
	}

	// @codingStandardsIgnoreStart

	/**
	 * inferPropertyFetch
	 *
	 * @param PropertyFetch $expr   Instance of PropertyFetch
	 * @param ClassLike     $inside Method inside the class
	 * @param Scope         $scope  The scope
	 *
	 * @return array
	 */
	public function inferPropertyFetch(PropertyFetch $expr, $inside, $scope) {
		list($class) = $this->inferType($inside, $expr->var, $scope);
		if (!empty($class) && $class[0] != "!") {
			if ($expr->name instanceof Node\Identifier) {

				$propName = strval($expr->name);
				if ($propName != "") {
					$classDef = $this->index->getAbstractedClass($class);
					if ($classDef) {
						list($prop) = Util::findAbstractedProperty($class, $propName, $this->index);
						if ($prop) {
							$type = $prop->getType();
							if (!empty($type)) {
								if ($type[0] == '\\') {
									$type = substr($type, 1);
								}

								$type2 = Scope::constFromDocBlock($type, $inside ? strval($inside->namespacedName) : "", $inside ? strval($inside->namespacedName) : "");
								return [$type2, 0];
							}
						}
					}
				}
			}
		}
		return [Scope::MIXED_TYPE, 0];
	}

	// @codingStandardsIgnoreStop

	protected function inferStaticCall(ClassLike $inside = null, Node\Expr\StaticCall $expr) {
		if (is_string($expr->name) && $expr->class instanceof Node\Name) {
			$class = strval($expr->name);
			$method = $this->index->getAbstractedMethod($class, $expr->name);
			if ($method) {
				return $this->inferKnownMethod($method, $inside, $class);
			}
		}
		return [Scope::MIXED_TYPE, 0];
	}

	/**
	 * @param ClassLike $inside -
	 * @param Expr      $expr   -
	 * @param Scope     $scope  -
	 * @return array
	 */
	protected function inferMethodCall(ClassLike $inside = null, Node\Expr\MethodCall $expr, Scope $scope) {
		if (gettype($expr->name) == "string") {
			list($class) = $this->inferType($inside, $expr->var, $scope);
			if (!empty($class) && $class[0] != "!") {

				$type = $this->tryMakeCheck($expr, $class);
				if ($type) {
					return [ $type, 0];
				}
				$method = $this->index->getAbstractedMethod($class, $expr->name);
				if ($method) {
					return $this->inferKnownMethod($method, $inside, $class);
				}
			}
		}
		return [Scope::MIXED_TYPE, 0];
	}

	/**
	 * @param ClassLike       $inside
	 * @param Expr\MethodCall $expr
	 * @param string          $class
	 * @return array [0=>type, 1=>attributes]
	 */
	protected function inferKnownMethod(MethodInterface $method, ClassLike $inside = null, $class) {
		$type = Scope::constFromName($method->getReturnType());
		if ($type) {
			return [$type, $method->hasNullableReturnType() ? Attributes::NULL_POSSIBLE : 0];
		}

		if (Config::shouldUseDocBlockForReturnValues()) {
			$type = Scope::constFromDocBlock(
				$method->getDocBlockReturnType(),
				$inside ? strval($inside->namespacedName) : "",
				$class
			);
			if ($type) {
				return [$type, 0];
			}
		}
	}

	protected function inferFunctionCall(ClassLike $inside=null,  FuncCall $expr, Scope $scope) {
		if ($expr->name instanceof Name) {
			$name=strtolower($expr->name);
			if(count($expr->args)>0) {
				list(,$attributes) = $this->inferType($inside, $expr->args[0]->value, $scope);
				if ($name=="intval" && count($expr->args) > 0) {
					return [Scope::INT_TYPE, $attributes & ~Attributes::NULL_POSSIBLE];
				}
				if ($name == "urlencode" && count($expr->args) > 0) {
					return [Scope::STRING_TYPE, ($attributes & ~Attributes::NULL_POSSIBLE) | Attributes::CLEAN_URL];
				}
				if ($name == "addslashes" || $name=="dechex" || $name=="binhex") {
					return [Scope::STRING_TYPE, ($attributes & ~Attributes::NULL_POSSIBLE)| Attributes::CLEAN_DB_TERM];
				}
				if($name=="dechex" || $name=="bin2hex") {
					return [Scope::STRING_TYPE, ($attributes & ~Attributes::NULL_POSSIBLE) | Attributes::CLEAN_DB_TERM | Attributes::CLEAN_HTML | Attributes::CLEAN_URL];
				}
				if($name=="htmlentities") {
					return [Scope::STRING_TYPE, ($attributes & ~Attributes::NULL_POSSIBLE) | Attributes::CLEAN_HTML];
				}
			}
			$func = $this->index->getAbstractedFunction($expr->name);
			if ($func) {
				$type = Scope::constFromName($func->getReturnType());
				if ($type) {
					return [$type, $func->hasNullableReturnType() ? Attributes::NULL_POSSIBLE : 0];
				}
			}
		}
		return [Scope::MIXED_TYPE, Attributes::NULL_POSSIBLE];
	}

	/**
	 * @param Expr\MethodCall $expr  The methodcall node from the AST.
	 * @param string          $class The class type that the call is made against.
	 * @return string
	 */
	protected function tryMakeCheck(Node\Expr\MethodCall $expr, $class) {
// IoC
		if (
			strcasecmp($class, "Core\\App\\App") == 0 &&
			$expr->name == "make"
		) {
			if (count($expr->args) == 1) {
				$arg0 = $expr->args[0]->value;
				if ($arg0 instanceof Expr\ClassConstFetch) {
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


}