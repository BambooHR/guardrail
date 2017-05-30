<?php namespace BambooHR\Guardrail;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

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
	 * @return string
	 * @todo This looks like a good place for a strategy pattern
	 */
	public function inferType(ClassLike $inside = null, Expr $expr=null, Scope $scope) {
		if ($expr instanceof AssignOp) {
			return $this->inferType($inside, $expr->expr, $scope);
		} else if ($expr instanceof Scalar) {
			return Scope::SCALAR_TYPE;
		} else if ($expr instanceof New_ && $expr->class instanceof Name) {
			$className = strval($expr->class);
			if (strcasecmp($className, "self") == 0) {
				$className = $inside ? strval($inside->namespacedName) : Scope::MIXED_TYPE;
			} else if (strcasecmp($className, "static") == 0) {
				$className = Scope::MIXED_TYPE;
			}
			return $className;
		} else if ($expr instanceof Node\Expr\Variable && gettype($expr->name) == "string") {
			$varName = strval($expr->name);
			if ($varName == "this" && $inside) {
				return strval($inside->namespacedName);
			}
			$scopeType = $scope->getVarType($varName);
			if ($scopeType != Scope::UNDEFINED) {
				return $scopeType;
			}
		} else if ($expr instanceof Closure) {
			return "callable";
		} else if ($expr instanceof FuncCall && $expr->name instanceof Name) {
			$func = $this->index->getAbstractedFunction($expr->name);
			if ($func) {
				$type = $func->getReturnType();
				if ($type) {
					return $type;
				}
			}
		} else if ( $expr instanceof Node\Expr\MethodCall && gettype($expr->name) == "string") {
			$class = $this->inferType($inside, $expr->var, $scope);
			if (!empty($class) && $class[0] != "!") {
				$method = $this->index->getAbstractedMethod($class, strval($expr->name));
				if ($method) {
					$type = $method->getReturnType();
					if ($type) {
						return $type;
					}
					/*
					$type = $method->getDocBlockReturnType();
					if($type) {
						return $type;
					}
					*/
				}
			}
		} else if ( $expr instanceof PropertyFetch ) {
			return $this->inferPropertyFetch($expr, $inside, $scope);
		} else if ( $expr instanceof ArrayDimFetch ) {
			$type = $this->inferType($inside, $expr->var, $scope);
			if (substr($type, -2) == "[]") {
				return substr($type, 0, -2);
			}
		} else if ($expr instanceof Clone_) {
			// A cloned node will be the same type as whatever we're cloning.
			return $this->inferType($inside, $expr->expr, $scope);
		}
		return Scope::MIXED_TYPE;
	}

	/**
	 * inferPropertyFetch
	 *
	 * @param PropertyFetch $expr   Instance of PropertyFetch
	 * @param string        $inside Method inside the class
	 * @param string        $scope  The scope
	 *
	 * @return string
	 */
	public function inferPropertyFetch(PropertyFetch $expr, $inside, $scope) {
		$class = $this->inferType($inside, $expr->var, $scope);
		if (!empty($class) && $class[0] != "!") {
			if (gettype($expr->name) == 'string') {
				/*
				$classDef = $this->index->getClass($class);
				if($classDef) {
					$prop = Util::findProperty($classDef, strval($expr->name), $this->index );
					if($prop) {
						$type = $prop->getAttribute("namespacedType") ?: "";
						if(!empty($type)) {
							if($type[0]=='\\') {
								$type=substr($type,1);
							}
							return $type;
						}
					}
				}
				*/
			}
		}
		return Scope::MIXED_TYPE;
	}
}