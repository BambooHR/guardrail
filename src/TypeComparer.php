<?php

namespace BambooHR\Guardrail;

use BambooHR\Guardrail\Evaluators\Expression\ArrayDimFetch;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use PhpParser\Node;
use PhpParser\Node\ComplexType;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\UnionType;

class TypeComparer
{
	const INFERRED_TYPE_ATTR = 'inferrer-type';

	function __construct(private SymbolTable $symbolTable) { }

	static public function identifierFromName(string $str): Identifier {
		static $identifier = [];
		if (!array_key_exists($str, $identifier)) {
			$identifier[$str] = new Identifier($str);
		}
		return $identifier[$str];
	}

	static private function compareUnionElements(Name|Identifier|IntersectionType $a, Name|Identifier|IntersectionType $b) {
		$aType = $a instanceof IntersectionType ? 1 : 0;
		$bType = $b instanceof IntersectionType ? 1 : 0;
		if ($aType != $bType) {
			return $aType <=> $bType;
		}
		if ($aType == 1) {
			$commonIndexes = min(count($a->types), count($b->types));
			for ($i = 0; $i <= $commonIndexes; ++$i) {
				$diff = strcmp($a->types[$i], $b->types[$i]);
				if ($diff !== 0) {
					return $diff;
				}
			}
			return count($a->types) <=> count($b->types);
		} else {
			return strcmp(strval($a), strval($b));
		}
	}

	private static function normalizeUnionType(UnionType $type): UnionType {
		// First, sort any intersections inside the union.
		foreach ($type->types as $subType) {
			if ($subType instanceof Node\IntersectionType) {
				self::normalizeIntersectionType($subType);
			}
		}
		usort($type->types, self::compareUnionElements(...));
		return $type;
	}


	private static function normalizeIntersectionType(IntersectionType $type): IntersectionType {
		usort($type->types, function($a, $b) {
			return strcmp(strval($a), strval($b));
		});
		return $type;
	}

	public static function normalizeType(ComplexType|Identifier|Name|null $type): ComplexType|Identifier|Name|null {
		if ($type instanceof Node\NullableType) {
			$type = new UnionType([self::identifierFromName("null"), $type->type]);
		}
		if ($type instanceof Node\UnionType) {
			return self::normalizeUnionType($type);
		}
		if ($type instanceof Node\IntersectionType) {
			return self::normalizeIntersectionType($type);
		}
		return $type;
	}

	public static function nameFromName(string $str): Name {
		static $name = [];
		if (!array_key_exists($str, $name)) {
			$name[$str] = new Name($str);
		}
		return $name[$str];
	}

	static function isExactMatch(ComplexType|Identifier|Name|null $a, ComplexType|Identifier|Name|null $b): bool {
		if ($a == null && $b == null) {
			return true;
		}

		if ($a instanceof Identifier && $b instanceof Identifier && strcasecmp($a->name, $b->name) == 0) {
			return strcasecmp($a->name, $b->name) == 0;
		}
		if ($a instanceof Name && $b instanceof Name) {
			return strcasecmp($a->getAttribute("namespacedName"), $b->getAttribute("namespacedName")) == 0;
		}
		if ($a instanceof IntersectionType && $b instanceof IntersectionType) {
			self::ifEveryType($a, fn($aType) =>
			self::ifAnyType($b, fn($bType) =>
			self::isExactMatch($aType, $bType)
			)
			);
		}
		if ($a instanceof UnionType && $b instanceof UnionType) {
			self::ifEveryType($a, fn($aType) =>
			self::ifAnyType($b, fn($bType) =>
			self::isExactMatch($aType, $bType)
			)
			);
			return true;
		}
		return false;
	}


	/**
	 * Given a chain of $Variable->PropertyFetch...->Identifier, produce a name
	 * Does not produce a name if there are any Array lookups in the chain or if the end isn't a string identifier.
	 *
	 */
	public static function getChainedElements(?Node $expr): array {
		while ($expr !== null) {
			if ($expr instanceof Node\Expr\Variable && is_string($expr->name)) {
				$entries[] = strval($expr->name);
				return array_reverse($entries);
			} elseif (
				($expr instanceof Node\Expr\PropertyFetch || $expr instanceof Node\Expr\NullsafePropertyFetch) &&
				$expr->name instanceof Identifier
			) {
				$entries[] = strval($expr->name);
				$expr = $expr->var;
			} else {
				break;
			}
		}
		return [];
	}

	function areSimpleTypesCompatible(Name|Identifier|null $target, Name|Identifier|UnionType|null $value, bool $strict): bool {
		if ($target == null) {
			return true;
		}
		if ($value == null) {
			return true;
		}
		$targetName = strtolower($target->getAttribute('namespacedName') ?: strval($target));
		$valueName = strtolower($value->getAttribute('namespacedName') ?: strval($value));

		//echo "Checking compatibility of $targetName and $valueName\n";
		if ($targetName == $valueName || $targetName == "mixed") {
			return true;
		}

		if ($this->symbolTable->isParentClassOrInterface($targetName, $valueName)) {
			return true;
		}

		if ($targetName == "string" && $this->symbolTable->isDefinedClass($valueName)) {
			if ($this->symbolTable->getAbstractedMethod($valueName, "__toString")) {
				return true;
			}
		}

		if (in_array($targetName, ["countable", "iterable"]) && $valueName == "array") {
			return true;
		}

		if ($targetName == "callable" && ($valueName == "closure" || $valueName == "array" || $valueName == "string")) {
			return true;
		}

		if ($targetName == "bool" && ($valueName == "true"  || $valueName == "false")) {
			return true;
		}

		// Floats can accept ints even in strict mode
		if ($targetName == "float" && $valueName == "int") {
			return true;
		}

		if (
			($targetName == "false" || $targetName == "true" || $targetName == "null") &&
			$valueName != $targetName
		) {
			return false;
		}

		if (!$strict) {
			if ($targetName == "mixed") {
				return true;
			}
			if ($valueName == "mixed") {
				return true;
			}
			if (Util::isScalarType($targetName) && (Util::isScalarType($valueName) || $valueName == "null")) {
				return true;
			}
		}
		return $valueName == $targetName;
	}

	/**
	 * Given a chain of $Variable->PropertyFetch...->Identifier, produce a name
	 * Does not produce a name if there are any Array lookups in the chain or if the end isn't a string identifier.
	 *
	 */
	static function getChainedPropertyFetchName(Node $rootNode): ?string {
		if (($rootNode instanceof Node\Expr\PropertyFetch  || $rootNode instanceof Node\Expr\NullsafePropertyFetch)
			&& $rootNode->name instanceof Identifier
		) {
			$left = self::getChainedPropertyFetchName($rootNode->var);
			return $left ? ($left . "->" . $rootNode->name) : null;
		} elseif ($rootNode instanceof Node\Expr\Variable && is_string($rootNode->name)) {
			return strval($rootNode->name);
		} else {
			return null;
		}
	}

	static function typeToString(ComplexType|Name|Identifier|null $type): string {
		if ($type === null) {
			return "(unknown)";
		} elseif ($type instanceof Name) {
			$vars = $type->getAttribute('templates', []);
			if (count($vars) > 0) {
				return $type . "<" . implode(",", $vars) . ">";
			} else {
				return strval($type);
			}
		} elseif ($type instanceof Identifier) {
			return strval($type);
		} elseif ($type instanceof Node\NullableType) {
			return "null|" . strval($type->type);
		} elseif ($type instanceof UnionType) {
			return implode("|", array_map(fn($type)=>self::typeToString($type), $type->types));

		} elseif ($type instanceof IntersectionType) {
			return "(" . implode("&", array_map(fn($type)=>self::typeToString($type), $type->types )) . ")";
		} else {
			// Should be unreachable
			return "ERROR(" . get_class($type) . ")";
		}
	}

	private function simpleTypeIsCompatibleWithIntersectionType(Name|Identifier $target, IntersectionType $valueType, bool $strict) {
		foreach ($valueType->types as $valueComponentType) {
			if ($this->areSimpleTypesCompatible($target, $valueComponentType, $strict)) {
				return true;
			}
		}
		return false;
	}

	function isContravariant(ComplexType|Name|Identifier|null $target, ComplexType|Name|Identifier|null $value) {
		if (is_null($target) || self::isNamedIdentifier($target, "mixed")) {
			if (is_null($value) || self::isNamedIdentifier($value, "mixed")) {
				return true;
			}
			return false;
		}
		return $this->isCompatibleWithTarget($value, $target, true);
	}

	function isCovariant(ComplexType|Name|Identifier|null $target, ComplexType|Name|Identifier|null $value) {
			if (is_null($value)) {
			if (is_null($target)) {
				return true;
			}
		}
		if (self::isNamedIdentifier($value, "mixed")) {
			return self::isNamedIdentifier($target, "mixed");
		}

		$ret = $this->isCompatibleWithTarget($target, $value, true);
		return $ret;
	}

	/**
	 * Null values indicate an unknown type.  Unknown types are always considerable compatible.  Mixed *targets* can
	 * accept any target.  Specific *targets* are too narrow to accept mixed *values*.
	 *
	 * @param ComplexType|Name|Identifier|null $target
	 * @param ComplexType|Name|Identifier|null $value
	 * @return bool
	 *
	 */
	function isCompatibleWithTarget(ComplexType|Name|Identifier|null $target, ComplexType|Name|Identifier|null $value, $forceStrict = false, $nullChecks = true): bool {
		if ($nullChecks) {
			if (is_null($target)) {
				return true;
			}
		}

		if (is_null($value)) {
			return true;
		}

		// Many target options, many values.  Every value option must match at least one target.
		$ret = self::ifEveryType($value, fn($valueType) =>
			self::ifAnyType($target, function($targetType) use ($forceStrict, $valueType) {
				if ($targetType instanceof IntersectionType) {
					$types = $targetType->types;
				} else {
					$types = [$targetType];
				}
				foreach ($types as $targetComponentType) {
					if ($valueType instanceof IntersectionType) {
						if (!$this->simpleTypeIsCompatibleWithIntersectionType($targetComponentType, $valueType, $forceStrict)) {
							return false;
						}
					} else {
						if (!$this->areSimpleTypesCompatible($targetComponentType, $valueType, $forceStrict)) {
							return false;
						}
					}
				}
				return true;
			})
		);
		return $ret;
	}

	static function removeNullOption(ComplexType|Identifier|Name|null $a): ComplexType|Identifier|Name|null {
		return self::removeNamedOption($a, "null");
	}


	static function removeNullOptions(Node\Expr\Variable|Node\Expr\PropertyFetch|Node\Expr\NullsafePropertyFetch|Node\Expr\ArrayDimFetch $expr, Scope\Scope $scope, int $line) {
		$first = $expr;
		while ($expr !== null && !($expr instanceof ArrayDimFetch)) {
			$name = self::getChainedPropertyFetchName($expr);
			if ($name == "") {
				return;
			}
			$type = $scope->getVarType($name) ? $scope->getVarType($name) : $expr->getAttribute(self::INFERRED_TYPE_ATTR);
			$newType = self::removeNullOption($type);
			$scope->setVarType($name, $newType, $line);
			if ($expr !== $first) {
				$scope->setVarUsed($name);
			}
			$expr = ($expr instanceof Node\Expr\PropertyFetch || $expr instanceof Node\Expr\NullsafePropertyFetch) ? $expr->var : null;
		}
	}

	static function removeNullInferences(Node\Expr\Variable|Node\Expr\PropertyFetch|Node\Expr\NullsafePropertyFetch|Node\Expr\ArrayDimFetch $expr, SymbolTable $symbolTable, Scope\Scope $scope, int $line) {
		$entries = self::getChainedElements($expr);
		$var = array_shift($entries);
		$classType  = self::removeNullOption($scope->getVarType($var));
		$scope->setVarType($var, $classType, $line);
		foreach ($entries as $name) {
			$propName = $var . "->" . $name;
			$classType = $scope->getVarType($propName);
			if (!$classType) {
				$classType = $scope->getVarType($var);
				$types = [];
				self::forEachAnyEveryType($classType, function ($type) use (&$types, $name, $symbolTable) {
					$property = Util::findAbstractedProperty(strval($type), $name, $symbolTable);
					if ($property?->getType()) {
						$types[] = $property->getType();
					}
				});
				$classType = self::removeNullOption(self::getUniqueTypes(...$types));
			}
			$scope->setVarType($propName, $classType, $line);
			$var = $propName;
		}
	}

	static function removeNamedOption(ComplexType|Identifier|Name|null $a, string $name): ComplexType|Identifier|Name|null {
		$ret = [];
		self::forEachType($a, function($el) use ($name, &$ret) {
			if (!self::isNamedIdentifier($el, $name)) {
				$ret[] = $el;
			}
		});
		$ret2 = self::getUniqueTypes(...$ret);
		return $ret2;
	}

	static function ifEveryType(ComplexType|Identifier|Name $node, callable $fn) {
		if ($node instanceof Node\NullableType) {
			$types = [self::identifierFromName("null"), $node->type ];
		} elseif ($node instanceof Identifier || $node instanceof Name || $node instanceof IntersectionType) {
			$types = [$node];
		} elseif ($node instanceof UnionType) {
			$types = $node->types;
		}
		foreach ($types as $type) {
			if (!call_user_func( $fn, $type )) {
				return false;
			}
		}
		return true;
	}

	static function ifAnyType(ComplexType|Identifier|Name|null $node, callable $fn) {
		if ($node === null) {
			$types = [null];
		} elseif ($node instanceof Node\NullableType) {
			$types = [self::identifierFromName("null"), $node->type];
		} elseif ($node instanceof Identifier || $node instanceof Name || $node instanceof IntersectionType) {
			$types = [$node];
		} else {
			$types = $node->types;
		}

		foreach ($types as $type) {
			if (call_user_func($fn, $type)) {
				return true;
			}
		}
		return false;
	}

	static function ifAnyTypeIsNull($node): bool {
		return self::ifAnyType($node, fn($type)=>self::isNamedIdentifier($type, "null"));
	}

	static function forEachType($node, callable $fn) {
		if ($node instanceof Node\NullableType) {
			call_user_func($fn, self::identifierFromName("null"));
			$node = $node->type;
		}
		if ($node instanceof Name || $node instanceof Identifier || $node instanceof IntersectionType) {
			call_user_func($fn, $node);
		} elseif ($node instanceof UnionType) {
			foreach ($node->types as $type) {
				call_user_func($fn, $type);
			}
		}
	}

	static function forEachAnyEveryType($node, callable $fn) {
		self::forEachType($node, function($type) use ($fn) {
			if ($type instanceof IntersectionType) {
				foreach ($type->types as $iType) {
					call_user_func($fn, $iType);
				}
			} else {
				call_user_func($fn, $type);
			}
		});
	}

	function isTraversable(ComplexType|Identifier|Name|null $type): bool {
		if (is_null($type)) {
			return true;
		}
		return self::ifEveryType($type, function($subType) {
			if ($subType) {
				if ($subType instanceof Node\Name or $subType instanceof Node\Identifier) {
					$typeStr = strval($subType);
					if (str_ends_with($typeStr, "[]")) {
						return true;
					}
					if (strcasecmp($typeStr, "array") == 0) {
						return true;
					}
					if (!$this->symbolTable->isParentClassOrInterface(\Traversable::class, $typeStr)) {
						return true;
					}
					if (!$this->symbolTable->isParentClassOrInterface('iterable', $typeStr)) {
						return true;
					}
				}
				return false;
			} else {
				// Unknown, type we have to assume it is safe.
				return true;
			}
		});
	}

	static function getUniqueTypes(...$types) {
		$used = [];
		$unknown = 0;
		foreach ($types as $list) {
			self::forEachType($list, function ($typeA) use (&$used, &$unknown) {
				if ($typeA == null) {
					$unknown = 1;
				} elseif (TypeComparer::isNamedIdentifier($typeA, "mixed") && $unknown != 1) {
					$unknown = 2;
				} else {
					$used[TypeComparer::typeToString($typeA)] = $typeA;
				}
			});
		}
		if ($unknown == 1 || count($used) == 0) {
			return null;
		} elseif ($unknown == 2) {
			return self::identifierFromName("mixed");
		}

		$used = array_values($used);
		if (count($used) == 1) {
			return $used[0];
		} else {
			return new UnionType($used);
		}
	}

	static function isNamedIdentifier(?Node $node, string $name): bool {
		return ($node instanceof Node\Identifier || $node instanceof Node\Name) && strcasecmp(strval($node), $name) == 0;
	}
}