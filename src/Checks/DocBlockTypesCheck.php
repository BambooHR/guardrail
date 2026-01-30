<?php

namespace BambooHR\Guardrail\Checks;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\TypeComparer;
use BambooHR\Guardrail\Util;
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
	 * run
	 *
	 * @param string         $fileName The name of the file we are parsing
	 * @param Node           $node     Instance of the Node
	 * @param ClassLike|null $inside   Instance of the ClassLike (the class we are parsing) [optional]
	 * @param Scope|null     $scope    Instance of the Scope (all variables in the current state) [optional]
	 *
	 * @return void
	 */
	public function run($fileName, Node $node, ?ClassLike $inside = null, ?Scope $scope = null) {
		/*
		if ($node instanceof FunctionLike) {
			$returnTypeOb = $node->getReturnType();

			$docBlockReturn = Util::mapClassName(
				$node->getAttribute("namespacedReturn"),
				$inside && isset($inside->namespacedName) ? strval($inside->namespacedName) : "",
				$inside && isset($inside->namespacedName) ? strval($inside->namespacedName) : ""
			);

			if (!empty($docBlockReturn)) {
				$tc=new TypeComparer($this->symbolTable);
				if ($returnTypeOb && $docBlockReturn && $tc->isCompatibleWithTarget($returnTypeOb, Scope::nameFromName($docBlockReturn))) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_DOC_BLOCK_MISMATCH, "Function return type ($return) doesn't match DocBlock return type($docBlockReturn");
				}
				if ($docBlockReturn[0] != "!") {
					$this->checkOrEmit($docBlockReturn, $fileName, $node, ErrorConstants::TYPE_DOC_BLOCK_RETURN, "Unknown function return type \"$docBlockReturn\" specified in DocBlock");
				}
			}
		} else if ($node instanceof PropertyProperty) {
			$docBlockType = $node->getAttribute("namespacedType");
			if ($docBlockType) {
				$docBlockType = Util::mapClassName($docBlockType,
					$inside && isset($inside->namespacedName) ? strval($inside->namespacedName) : "",
					$inside && isset($inside->namespacedName) ? strval($inside->namespacedName) : ""
				);

				$this->checkOrEmit($docBlockType, $fileName, $node, ErrorConstants::TYPE_DOC_BLOCK_VAR, "Unknown property type \"$docBlockType\" specified in DocBlock");
			}
		}*/
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
				} elseif (!Util::isScalarType($typeName) && !$this->symbolTable->isDefinedClass($typeName)) {
					$this->emitError($fileName, $node, $class, $message);
				}
			}
		}
	}
}
