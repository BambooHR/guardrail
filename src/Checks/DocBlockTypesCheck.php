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

	static $types = [
		"bool" => 1,
		"float" => 1,
		"double" => 1,
		"false" => 1,
		"true" => 1,
		"self" => 1,
		"callable" => 1,
		"int" => 1,
		"array" => 1,
		"callable" => 1,
		"void" => 1,
		"string" => 1,
		"mixed" => 1,
		"object" => 1,
		"resource" => 1,
		"null" => 1,
		"integer" => 1,
		"boolean" => 1,
		Scope::MIXED_TYPE => 1
	];

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
		$typeName = strtolower($typeName);
		return $typeName == '' || array_key_exists($typeName, self::$types);
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
		$typeName = str_replace("[]", "", $typeName);
		foreach (explode('|', $typeName) as $typeName) {
			$typeName = trim($typeName);
			if ($typeName) {
				if ($typeName == "type" || strrpos($typeName, "\\type") == strlen($typeName) - 5) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_DOC_BLOCK_TYPE, $message);
				} else if (!self::isScalar($typeName) && !$this->symbolTable->isDefinedClass($typeName)) {
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
		if ($node instanceof FunctionLike) {
			$return = strval($node->getReturnType() ?: "");
			$docBlockReturn = Scope::constFromDocBlock(
				$node->getAttribute("namespacedReturn"),
				$inside && isset($inside->namespacedName) ? strval($inside->namespacedName) : "",
				$inside && isset($inside->namespacedName) ? strval($inside->namespacedName) : ""
			);

			if (!empty($docBlockReturn)) {
				if ($docBlockReturn != $return && !empty($return)) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_DOC_BLOCK_MISMATCH, "Function return type ($return) doesn't match DocBlock return type($docBlockReturn");
				}
				if ($docBlockReturn[0] != "!") {
					$this->checkOrEmit($docBlockReturn, $fileName, $node, ErrorConstants::TYPE_DOC_BLOCK_RETURN, "Unknown function return type \"$docBlockReturn\" specified in DocBlock");
				}
			}
		} else if ($node instanceof PropertyProperty) {
			$docBlockType = $node->getAttribute("namespacedType");
			$docBlockType = Scope::constFromDocBlock(
				$docBlockType,
				$inside ? strval($inside->namespacedName) : "",
				$inside ? strval($inside->namespacedName) : ""
			);

			if ($docBlockType && $docBlockType[0] != "!") {
				$this->checkOrEmit($docBlockType, $fileName, $node, ErrorConstants::TYPE_DOC_BLOCK_VAR, "Unknown property type \"$docBlockType\" specified in DocBlock");
			}
		}
	}

}