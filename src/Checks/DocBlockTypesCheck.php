<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\Checks;


use BambooHR\Guardrail\Scope;
use PhpParser\Node;

class DocBlockTypesCheck extends BaseCheck
{
	const TYPE_DOCBLOCK_PARAM="Standard.DocBlock.Param";
	const TYPE_DOCBLOCK_RETURN="Standard.DocBlock.Return";
	const TYPE_DOCBLOCK_VAR="Standard.DocBlock.Variable";
	const TYPE_DOCBLOCK_TYPE="Standard.DocBlock.Type";
	const TYPE_DOCBLOCK_MISMATCH="Standard.DocBlock.Mismatch";

	function getCheckNodeTypes() {
		return [Node\Stmt\Function_::class, Node\Stmt\ClassMethod::class, Node\Stmt\PropertyProperty::class];
	}

	static function isScalar($typeName) {
		$types=["bool","float","double","false","true","self","callable","int","array","callable","void","string","mixed","object","resource","null","integer","boolean","",Scope::MIXED_TYPE];
		return in_array(strtolower($typeName), $types);
	}

	function checkOrEmit($typeName, $fileName, $node, $class, $message) {
		foreach(explode('|', $typeName) as $typeName) {
			$typeName = str_replace("[]","", $typeName);
			if ($typeName && !self::isScalar($typeName) && !$this->symbolTable->getAbstractedClass($typeName)) {
				if ($typeName == "type" || strrpos($typeName, "\\type") == strlen($typeName) - 5) {
					$this->emitError($fileName, $node, self::TYPE_DOCBLOCK_TYPE, $message);
				} else {
					$this->emitError($fileName, $node, $class, $message);
				}
			}
		}
	}

	function run($fileName, $node, Node\Stmt\ClassLike $inside = null, Scope $scope = null) {
		if( $node instanceof Node\FunctionLike) {
			$return = strval( $node->getReturnType() ?: "");
			$docBlockReturn = $node->getAttribute("namespacedReturn");

			if(!empty($docBlockReturn)) {
				if ($docBlockReturn != $return && !empty($return)) {
					$this->emitError($fileName, $node, self::TYPE_DOCBLOCK_MISMATCH, "Function return type ($return) doesn't match docblock return type($docBlockReturn");
				}
				$this->checkOrEmit($docBlockReturn, $fileName, $node, self::TYPE_DOCBLOCK_RETURN, "Unknown function return type \"$docBlockReturn\" specified in docblock");
			}
		} else if($node instanceof Node\Stmt\PropertyProperty) {
			$docBlockType = $node->getAttribute("namespacedType");
			if($docBlockType) {
				$this->checkOrEmit($docBlockType, $fileName, $node, self::TYPE_DOCBLOCK_VAR,"Unknown property type \"$docBlockType\" specified in docblock");
			}
		}
	}


}