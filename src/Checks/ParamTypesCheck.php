<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Util;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use BambooHR\Guardrail\Scope;

class ParamTypesCheck extends BaseCheck
{
	function getCheckNodeTypes() {
		return [
			\PhpParser\Node\Stmt\ClassMethod::class,
			\PhpParser\Node\Stmt\Function_::class,
			\PhpParser\Node\Expr\Closure::class
		];
	}

	function isAllowed($name, ClassLike $inside=null) {
		$nameLower = strtolower($name);
		if($nameLower=="self" && $inside instanceof Class_) {
			return true;
		}
		if ($nameLower != "" && !Util::isLegalNonObject($name)) {
			$class = $this->symbolTable->isDefinedClass($name);
			if (!$class && !$this->symbolTable->ignoreType($name)) {
				return false;
			}
		}
		return true;
	}

	function run($fileName, $method, ClassLike $inside=null, Scope $scope=null) {
		if(!property_exists($method,'name')) {
			$displayName="closure function";
		} else {
			$displayName=$method->name;
		}

		foreach ($method->params as $index => $param) {
			if($param->type) {
				$name = strval($param->type);
				if(!$this->isAllowed( $name, $inside )) {
					$this->emitError($fileName, $method, self::TYPE_UNKNOWN_CLASS, "Reference to an unknown type '$name'' in parameter $index of $displayName");
				}
			}
		}

		if($method->returnType) {
			$returnType = strval($method->returnType);
			if(!$this->isAllowed($returnType, $inside)) {
				$this->emitError($fileName, $method, self::TYPE_UNKNOWN_CLASS, "Reference to an unknown type '$returnType' in return value of $displayName");
			}
		}
	}
}