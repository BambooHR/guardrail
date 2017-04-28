<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail;

use BambooHR\Guardrail\Scope;
use PhpParser\Node;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\Util;


class TypeInferrer
{
	/** @var SymbolTable */
	private $index;
	function __construct(SymbolTable $table) {
		$this->index= $table;
	}

	/**
	 * Do some simplistic checks to see if we can figure out object type.  If we can, then we can check method calls
	 * using that variable for correctness.
	 * @param Node\Expr $expr
	 * @param Scope     $scope
	 * @return string
	 */
	function inferType(Node\Stmt\ClassLike $inside = null, Node\Expr $expr=null, Scope $scope) {
		if ($expr instanceof Node\Expr\AssignOp) {
			return $this->inferType($inside, $expr->expr, $scope);
		} else if ($expr instanceof Node\Scalar) {
			return Scope::SCALAR_TYPE;
		} else if ($expr instanceof Node\Expr\New_ && $expr->class instanceof Node\Name) {
			$className = strval($expr->class);
			if (strcasecmp($className, "self") == 0) {
				$className = $inside ? strval($inside->namespacedName) : Scope::MIXED_TYPE;
			} else if (strcasecmp($className, "static") == 0) {
				$className = Scope::MIXED_TYPE;
			}
			return $className;
		} else if ($expr instanceof Node\Expr\Variable && gettype($expr->name) == "string") {
			$varName = strval($expr->name);
			if($varName=="this" && $inside) {
				return strval($inside->namespacedName);
			}
			$scopeType = $scope->getVarType($varName);
			if ($scopeType != Scope::UNDEFINED) {
				return $scopeType;
			}
		} else if ($expr instanceof Node\Expr\Closure) {
			return "callable";
		} else if ($expr instanceof Node\Expr\FuncCall && $expr->name instanceof Node\Name) {
			$func = $this->index->getFunction($expr->name);
			/*
			if($func) {
				$namespacedReturn =$func->getAttribute("namespacedReturn");
				return $namespacedReturn ?: Scope::MIXED_TYPE;
			}*/
		} else if( $expr instanceof Node\Expr\MethodCall && gettype($expr->name)=="string") {
			$class = $this->inferType($inside, $expr->var, $scope);
			if(!empty($class) && $class[0]!="!") {
				$method = $this->index->getAbstractedMethod($class, strval($expr->name));
				if($method) {
					$type = $method->getReturnType();
					if($type) {
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
		} else if( $expr instanceof Node\Expr\PropertyFetch ) {
			return $this->inferPropertyFetch($expr, $inside, $scope);
		} else if( $expr instanceof Node\Expr\ArrayDimFetch ) {
			$type = $this->inferType($inside, $expr->var, $scope);
			if(substr($type,-2)=="[]") {
				return substr($type,0,-2);
			}
		}
		return Scope::MIXED_TYPE;
	}

	function inferPropertyFetch(Node\Expr\PropertyFetch $expr, $inside, $scope) {
		$class = $this->inferType($inside, $expr->var, $scope);
		if(!empty($class) && $class[0]!="!") {
			if(gettype($expr->name)=='string') {
				$classDef = $this->index->getClass($class);
				if($classDef) {
					$prop = Util::findProperty($classDef, strval($expr->name), $this->index );
					if($prop) {
						/*
						$type = $prop->getAttribute("namespacedType") ?: "";
						if(!empty($type)) {
							if($type[0]=='\\') {
								$type=substr($type,1);
							}
							return $type;
						}*/
					}
				}
			}
		}
		return Scope::MIXED_TYPE;
	}
}