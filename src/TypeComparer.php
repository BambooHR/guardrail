<?php

namespace BambooHR\Guardrail;

use BambooHR\Guardrail\SymbolTable\SymbolTable;
use PhpParser\Node;
use PhpParser\Node\ComplexType;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\UnionType;

class TypeComparer
{
	const INFERRED_TYPE_ATTR='inferrer-type';

	function __construct(private SymbolTable $symbolTable) { }

	static public function identifierFromName(string $str):Identifier {
		static $identifier = [];
		if(!array_key_exists($str, $identifier)) {
			$identifier[$str]=new Identifier($str);
		}
		return $identifier[$str];
	}

	static public function nameFromName(string $str):Name {
		static $name = [];
		if(!array_key_exists($str, $name)) {
			$name[$str]=new Name($str);
		}
		return $name[$str];
	}

	static function isExactMatch(ComplexType|Identifier|Name|null $a, ComplexType|Identifier|Name|null $b):bool {
		if ($a==null && $b==null) {
			return true;
		}

		if ($a instanceof Identifier && $b instanceof Identifier && strcasecmp($a->name, $b->name)==0) {
			return strcasecmp($a->name, $b->name)==0;
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

	function areSimpleTypesCompatible(Name|Identifier|null $target, Name|Identifier|UnionType|null $value, bool $strict): bool {
		if($target == null || $value == null) {
			return true;
		}
		$targetName = strtolower($target->getAttribute('namespacedName') ?: strval($target));
		$valueName = strtolower($value->getAttribute('namespacedName') ?: strval($value));


		if ($targetName==$valueName || $targetName=="mixed") {
			return true;
		}

		if ($this->symbolTable->isParentClassOrInterface($targetName, $valueName)) {
			return true;
		}

		if ($targetName=="string" && $this->symbolTable->isDefinedClass($valueName)) {
			if ($this->symbolTable->getAbstractedMethod($valueName,"__toString")) {
				return true;
			}
		}

		if (in_array($targetName, ["countable", "iterable"]) && $valueName=="array") {
			return true;
		}

		if ($targetName=="array" && str_ends_with($valueName,"[]")) {
			$valueArrayType=substr($valueName,0,-2);
			if (Util::isScalarType($valueArrayType)) {
				return true;
			} else if ($this->symbolTable->isDefinedClass($valueArrayType)) {
				return true;
			}
		}

		if($targetName=="callable" && ($valueName=="closure" || $valueName=="array" || $valueName=="string")) {
			return true;
		}

		if ($targetName=="bool" && ($valueName=="true"  || $valueName=="false")) {
			return true;
		}

		// Floats can accept ints even in strict mode
		if ($targetName == "float" && $valueName=="int") {
			return true;
		}

		if (!$strict) {
			if ($targetName=="mixed" || $valueName=="mixed") {
				return true;
			}
			if (Util::isScalarType($targetName) && (Util::isScalarType($valueName) || $valueName=="null")) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Given a chain of $Variable->PropertyFetch...->Identifier, produce a name
	 * Does not produce a name if there are any Array lookups in the chain or if the end isn't a string identifier.
	 *
	 */
	static function getChainedPropertyFetchName(Node $rootNode):?string {
		if ($rootNode instanceof Node\Expr\PropertyFetch && $rootNode->name instanceof Identifier) {
			$left = self::getChainedPropertyFetchName($rootNode->var);
			return $left ? ($left."->".$rootNode->name) : null;
		} else if ($rootNode instanceof Node\Expr\ArrayDimFetch) {
			$left = self::getChainedPropertyFetchName($rootNode->var);
			return $left;
		} else if ($rootNode instanceof Node\Expr\Variable && is_string($rootNode->name)) {
			return strval($rootNode->name);
		} else {
			return null;
		}
	}

	static function typeToString(ComplexType|Name|Identifier|null $type):string {
		if ($type === null) {
			return "mixed";
		} else if($type instanceof Name || $type instanceof  Identifier) {
			return strval($type);
		} else if($type instanceof Node\NullableType) {
			return "(null|".strval($type->type).")";
		} else if($type instanceof UnionType) {
			return "(".implode("|", array_map(fn($type)=>self::typeToString($type), $type->types)).")";

		} else if ($type instanceof IntersectionType) {
			return "(".implode("&", array_map(fn($type)=>self::typeToString($type), $type->types )).")";
		} else {
			// Should be unreachable
			return "ERROR(".get_class($type).")";
		}
	}

	private function simpleTypeIsCompatibleWithIntersectionType(Name|Identifier $target, IntersectionType $valueType, bool $strict) {
		foreach($valueType->types as $valueComponentType) {
			if ($this->areSimpleTypesCompatible($target, $valueComponentType, $strict)) {
				return true;
			}
		}
		return false;
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
	function isCompatibleWithTarget(ComplexType|Name|Identifier|null $target, ComplexType|Name|Identifier|null $value, Scope $scope ) : bool {

		if ($target === NULL || $value === null) {
			return true;
		}

		// Many target options, many values.  Every value option must match at least one target.
		$ret = self::ifEveryType($value, fn($valueType) =>
		self::ifAnyType($target, function($targetType) use ($scope, $valueType) {

			if($targetType instanceof IntersectionType) {
				$types = $targetType->types;
			} else {
				$types = [$targetType];
			}


			foreach ($types as $targetComponentType) {
				if ($valueType instanceof IntersectionType) {
					if (!$this->simpleTypeIsCompatibleWithIntersectionType($targetComponentType, $valueType, $scope->isStrict())) {
						return false;
					}
				} else {
					if (!$this->areSimpleTypesCompatible($targetComponentType, $valueType, $scope->isStrict())) {
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
		return self::removeNamedOption($a,"null");
	}

	static function removeNamedOption(ComplexType|Identifier|Name|null $a, string $name): ComplexType|Identifier|Name|null {
		$ret = [];
		self::forEachType($a, function($el) use ($name, &$ret) {
			if(!self::isNamedIdentifier($el,$name)) {
				$ret[]=$el;
			}
		});
		return self::getUniqueTypes(...$ret);
	}

	static function ifEveryType(ComplexType|Identifier|Name $node, callable $fn) {
		if ($node instanceof Node\NullableType) {
			$types = [TypeComparer::identifierFromName("null"), $node->type ];
		} else if ($node instanceof Identifier || $node instanceof Name || $node instanceof IntersectionType) {
			$types = [$node];
		} else if ($node instanceof UnionType) {
			$types = $node->types;
		}
		foreach($types as $type) {
			if (!call_user_func( $fn, $type )) {
				return false;
			}
		}
		return true;
	}

	static function ifAnyType(ComplexType|Identifier|Name|null $node, callable $fn) {
		if ($node === null) {
			$types = [null];
		} else if ($node instanceof Node\NullableType) {
			$types = [TypeComparer::identifierFromName("null"), $node->type];
		} else if ($node instanceof Identifier || $node instanceof Name || $node instanceof IntersectionType) {
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

	static function ifAnyTypeIsNull($node):bool {
		return self::ifAnyType($node, fn($type)=>self::isNamedIdentifier($type,"null"));
	}

	static function forEachType($node, callable $fn) {
		if ($node instanceof Node\NullableType) {
			call_user_func($fn, TypeComparer::identifierFromName("null"));
			$node = $node->type;
		}
		if ($node instanceof Name || $node instanceof Identifier) {
			call_user_func($fn, $node);
		} else if($node instanceof UnionType) {
			foreach ($node->types as $type) {
				call_user_func($fn, $type);
			}
		}
	}

	static function forEachAnyEveryType($node, callable $fn) {
		self::forEachType($node, function($type) use ($fn) {
			if ($type instanceof IntersectionType) {
				foreach($type->types as $iType) {
					call_user_func($fn, $iType);
				}
			} else {
				call_user_func($fn, $type);
			}
		});
	}

	function isTraversable(ComplexType|Identifier|Name|null $type):bool {
		return $type === null || TypeComparer::ifEveryType($type, function($subType) {
				if ($subType) {
					if($subType instanceof Node\Name or $subType instanceof Node\Identifier) {
						$typeStr=strval($subType);
						if (str_ends_with($typeStr,"[]")) {
							return true;
						}
						if (strcasecmp($typeStr,"array") == 0) {
							return true;
						}
						if (!$this->symbolTable->isParentClassOrInterface(\Traversable::class, $typeStr)) {
							return true;
						}
						if (!$this->symbolTable->isParentClassOrInterface(\Iterable::class, $typeStr)) {
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
		foreach($types as $list) {
			self::forEachType($list, function ($typeA) use (&$used, &$unknown) {
				if ($typeA == null) {
					$unknown = 1;
				} else if (TypeComparer::isNamedIdentifier($typeA,"mixed") && $unknown != 1) {
					$unknown = 2;
				} else {
					$used[strval($typeA)] = $typeA;
				}
			});
		}
		if ($unknown == 1 || count($used) == 0){
			return null;
		} else if ($unknown == 2) {
			return TypeComparer::identifierFromName("mixed");
		}

		$used=array_values($used);
		if (count($used)==1) {
			return $used[0];
		} else {
			return new UnionType($used);
		}
	}

	static function isNamedIdentifier(?Node $node, string $name):bool {
		return ($node instanceof Node\Identifier || $node instanceof Node\Name) && strcasecmp(strval($node), $name) == 0;
	}
}