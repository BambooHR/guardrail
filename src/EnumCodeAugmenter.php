<?php

namespace BambooHR\Guardrail;

use BambooHR\Guardrail\Checks\ErrorConstants;
use BambooHR\Guardrail\Output\OutputInterface;
use PhpParser\Node;
use PhpParser\Builder\Property;
use PhpParser\Builder\Param;


/**
 * Class EnumCodeAugmenter
 *
 * Simple enum classes are expected to have a name property, a cases() method, and implement the UnitEnum interface.
 * Backed enum classes are expected to have a name and value property, a cases(), from(), and tryFrom() method, and
 * implement the BackedEnum interface.
 *
 * This class adds these properties and methods to the enum class so that future checks can be made against them.
 *
 * @package BambooHR\Guardrail
 *
 */
class EnumCodeAugmenter {
	static public function addEnumPropsAndMethods(Node\Stmt\Enum_ $enum, string $fileName, ?OutputInterface $output=null) {
		$isBacked = !is_null($enum->scalarType);
		$property = new Property("name");
		$property->setType(new Node\Identifier("string"));
		$property->makeReadonly();
		$enum->stmts[] = $property->getNode();

		if ($enum->getMethod("cases")) {
			$output?->emitError(self::class, $fileName, $enum->getLine(), ErrorConstants::TYPE_ENUM_RESERVED_METHOD, "Enum method 'cases' is reserved");
		}
		$enum->stmts[] = new Node\Stmt\ClassMethod("cases", ["returnType" => "array", "flags" => Node\Stmt\Class_::MODIFIER_STATIC | Node\Stmt\Class_::MODIFIER_PUBLIC]);


		if ($isBacked) {
			$property = new Property("value");
			$property->makeReadonly();
			$property->setType($enum->scalarType);
			$enum->stmts[] = $property->getNode();

			$enumName = $enum->namespacedName->toString();
			$param = (new Param("fromValue"))->setType($enum->scalarType);
			if ($enum->getMethod("tryFrom") || $enum->getMethod("from")) {
				$output?->emitError(self::class, $fileName, $enum->getLine(), ErrorConstants::TYPE_ENUM_RESERVED_METHOD, "Enum method 'from' or 'tryFrom' is reserved");
			}
			$enum->stmts[] = new Node\Stmt\ClassMethod("tryFrom", ["returnType" => $enumName, "flags" => Node\Stmt\Class_::MODIFIER_STATIC, 'params' => [$param->getNode()]]);
			$enum->stmts[] = new Node\Stmt\ClassMethod("from", ["returnType" => $enumName, "flags" => Node\Stmt\Class_::MODIFIER_STATIC, 'params' => [$param->getNode()]]);
		}
		$implements = ($isBacked ? "BackedEnum" : "UnitEnum");
		if (!in_array($implements, $enum->implements)) {
			$enum->implements[] = new Node\Name($implements);
		}
	}
}
