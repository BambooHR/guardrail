<?php namespace BambooHR\Guardrail\Checks;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Scope;
use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\PropertyProperty;

/**
 * Class DocBlockTypesCheck
 *
 * @package BambooHR\Guardrail\Checks
 */
class DocBlockTypesCheck extends BaseCheck {

	/**
	 * getCheckNodeTypes
	 *
	 * @return array
	 */
	public function getCheckNodeTypes() {
		return [Node\Stmt\Function_::class, Node\Stmt\ClassMethod::class, PropertyProperty::class];
	}

	/**
	 * isScalar
	 *
	 * @param string $typeName The type name
	 *
	 * @return bool
	 */
	static public function isScalar($typeName) {
		$types = ["bool","float","double","false","true","self","callable","int","array","callable","void","string","mixed","object","resource","null","integer","boolean","",Scope::MIXED_TYPE];
		return in_array(strtolower($typeName), $types);
	}

	/**
	 * checkOrEmit
	 *
	 * @param string $typeName The type name
	 * @param string $fileName The file name
	 * @param string $node     The node
	 * @param string $class    The class
	 * @param string $message  The message
	 *
	 * @return void
	 */
	public function checkOrEmit($typeName, $fileName, $node, $class, $message) {
		foreach (explode('|', $typeName) as $typeName) {
			$typeName = str_replace("[]", "", $typeName);
			if ($typeName && !self::isScalar($typeName) && !$this->symbolTable->isDefinedClass($typeName)) {
				if ($typeName == "type" || strrpos($typeName, "\\type") == strlen($typeName) - 5) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_DOC_BLOCK_TYPE, $message);
				} else {
					$this->emitError($fileName, $node, $class, $message);
				}
			}
		}
	}

	/**
	 * run
	 *
	 * @param string         $fileName The name of the file we are parsing
	 * @param Node           $node     Instance of the Node
	 * @param ClassLike|null $inside   Instance of the ClassLike (the class we are parsing) [optional]
	 * @param Scope|null     $scope    Instance of the Scope (all variables in the current state) [optional]
	 *
	 * @return void
	 */
	public function run($fileName, Node $node, ClassLike $inside = null, Scope $scope = null) {
		if ( $node instanceof FunctionLike) {
			$return = strval( $node->getReturnType() ?: "");
			$docBlockReturn = $node->getAttribute("namespacedReturn");

			if (!empty($docBlockReturn)) {
				if ($docBlockReturn != $return && !empty($return)) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_DOC_BLOCK_MISMATCH, "Function return type ($return) doesn't match DocBlock return type($docBlockReturn");
				}
				$this->checkOrEmit($docBlockReturn, $fileName, $node, ErrorConstants::TYPE_DOC_BLOCK_RETURN, "Unknown function return type \"$docBlockReturn\" specified in DocBlock");
			}
		} else if ($node instanceof PropertyProperty) {
			$docBlockType = $node->getAttribute("namespacedType");
			if ($docBlockType) {
				$this->checkOrEmit($docBlockType, $fileName, $node, ErrorConstants::TYPE_DOC_BLOCK_VAR, "Unknown property type \"$docBlockType\" specified in DocBlock");
			}
		}
	}

}