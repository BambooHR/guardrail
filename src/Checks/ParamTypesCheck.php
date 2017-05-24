<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Util;
use PhpParser\Node;
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

	/**
	 * run
	 *
	 * @param string         $fileName The name of the file we are parsing
	 * @param Node           $node     Instance of the Node
	 * @param ClassLike|null $inside   Instance of the ClassLike (the class we are parsing) [optional]
	 * @param Scope|null     $scope    Instance of the Scope (all variables in the current state) [optional]
	 *
	 * @return mixed
	 */
	public function run($fileName, Node $node, ClassLike $inside=null, Scope $scope=null) {
		if(!property_exists($node,'name')) {
			$displayName="closure function";
		} else {
			$displayName=$node->name;
		}

		foreach ($node->params as $index => $param) {
			if($param->type) {
				$name = strval($param->type);
				if(!$this->isAllowed( $name, $inside )) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_UNKNOWN_CLASS, "Reference to an unknown type '$name'' in parameter $index of $displayName");
				}
			}
		}

		if($node->returnType) {
			$returnType = strval($node->returnType);
			if(!$this->isAllowed($returnType, $inside)) {
				$this->emitError($fileName, $node, ErrorConstants::TYPE_UNKNOWN_CLASS, "Reference to an unknown type '$returnType' in return value of $displayName");
			}
		}
	}
}