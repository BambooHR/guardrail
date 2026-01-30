<?php

namespace BambooHR\Guardrail;

use PhpParser\Node;
use PhpParser\Builder\Property;
use PhpParser\Builder\Param;

class EnumCodeAugmenter {
	static public function addEnumPropsAndMethods(Node\Stmt\Enum_ $enum) {
		$isBacked = !is_null($enum->scalarType);
		$property = new Property("name");
		$property->setType(new Node\Identifier("string"));
		$property->makeReadonly();
		$enum->stmts[] = $property->getNode();
		$enum->stmts[] = new Node\Stmt\ClassMethod("cases", ["returnType" => "array", "flags" => Node\Stmt\Class_::MODIFIER_PUBLIC | Node\Stmt\Class_::MODIFIER_STATIC]);
		if ($isBacked) {
			$enum->stmts[] = new Node\Stmt\ClassMethod("values", ["returnType" => "array"]);
			$property = new Property("value");
			$property->makeReadonly();
			$property->setType($enum->scalarType);
			$enum->stmts[] = $property->getNode();

			$enumName = $enum->namespacedName->toString();
			$param = (new Param("fromValue"))->setType($enum->scalarType);
			$enum->stmts[] = new Node\Stmt\ClassMethod("tryFrom", ["returnType" => $enumName, "flags" => Node\Stmt\Class_::MODIFIER_STATIC, 'params' => [$param->getNode()]]);
			$enum->stmts[] = new Node\Stmt\ClassMethod("from", ["returnType" => $enumName, "flags" => Node\Stmt\Class_::MODIFIER_STATIC, 'params' => [$param->getNode()]]);
		}
	}
}
