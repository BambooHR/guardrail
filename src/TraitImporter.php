<?php namespace BambooHR\Guardrail;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\NodeVisitors\ForEachNode;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Stmt\TraitUseAdaptation;
use BambooHR\Guardrail\Exceptions\UnknownTraitException;
use BambooHR\Guardrail\SymbolTable\SymbolTable;

/**
 * Class TraitImportingVisitor
 *
 * This visitor modifies the tree by replacing Use statements in traits/classes with
 * the appropriate methods and properties.
 */
class TraitImporter {

	/** @var  SymbolTable */
	private $index;

	/**
	 * TraitImporter constructor.
	 *
	 * @param SymbolTable $index Instance of SymbolTable
	 */
	public function __construct( SymbolTable $index) {
		$this->index = $index;
	}

	/**
	 * @param mixed $fileName
	 * @param int $line
	 * @param NodeVisitors\Interface_|string|Class_|NodeVisitors\Trait_ $trait
	 * @param array $properties
	 * @param array $methods
	 * @param string $traitName
	 * @return array
	 */
	private function attachTraitLineNumbers(mixed $fileName, int $line, array $stmts, string $traitName) : array {
		$properties = $methods = $constants = [];
		$apply = function (Node $node) use ($fileName, $line) {
			$node->setAttribute('importedFromTrait', $fileName);
			$node->setAttribute('importedOnLine', $line);
		};

		$list = array_filter($stmts, function($stmt) {
			return $stmt instanceof Node\Stmt\Property || $stmt instanceof Node\Stmt\ClassMethod || $stmt instanceof Node\Stmt\ClassConst;
		});
		ForEachNode::run($list, $apply);

		foreach ($list as $stmt) {
			if ($stmt instanceof Node\Stmt\Property) {
				$props = clone $stmt;
				$properties[] = $props;
			} elseif ($stmt instanceof Node\Stmt\ClassMethod) {
				$method = clone $stmt;
				$methods[strval($stmt->name)][$traitName] = $method;
			} elseif ($stmt instanceof Node\Stmt\ClassConst) {
				$const = clone $stmt;
				$constants[] = $const;
			}
		}
		return array($properties, $methods, $constants);
	}

	/**
	 * resolveAdaptations
	 *
	 * @param TraitUseAdaptation[] $adaptations Array of Instances of TraitUseAdaptation
	 * @param array                $methods     The list of methods
	 *
	 * @return array
	 */
	private function resolveAdaptations(array $adaptations, array $methods) {
		foreach ($adaptations as $adaptation) {
			$adaptationMethodStr = strval($adaptation->method);
			if ($adaptation instanceof Node\Stmt\TraitUseAdaptation\Alias) {

				// Alias adaptation renames the alias
				if (!array_key_exists($adaptationMethodStr, $methods)) {
					echo "Attempt to rename a method  $adaptationMethodStr() that hasn't been imported";
					continue;
				}

				/** @var Node\Stmt\ClassMethod $method */
				if (strval($adaptation->trait) == "") {
					if (count($methods[$adaptationMethodStr]) > 1) {
						echo "Attempt to rename a method $adaptationMethodStr() without a trait name when importing from multiple implementations";
						continue;
					}
					$method = end($methods[$adaptationMethodStr]);
					$traitName = key($methods[$adaptationMethodStr]);
				} else {
					$method = $methods[$adaptationMethodStr][strval($adaptation->trait)];
					$traitName = strval($adaptation->trait);
				}

				if ($adaptation->newModifier != null) {
					$method->flags = $method->flags & ~(Class_::MODIFIER_PRIVATE | Class_::MODIFIER_PROTECTED | Class_::MODIFIER_PUBLIC) | $adaptation->newModifier;
				}

				if ($adaptation->newName != null) {
					$method->name = $adaptation->newName;

					// Unset it from the old name.
					unset($methods[$adaptationMethodStr][$traitName]);
					// Add it with the new name.
					$methods[strval($adaptation->newName)][$traitName] = $method;
				}
			} else if ($adaptation instanceof Node\Stmt\TraitUseAdaptation\Precedence) {
				// Instance of adaptation ignores the method from a list of traits.
				foreach ($adaptation->insteadof as $name) {
					if (!isset($methods[$adaptationMethodStr][$name])) {
						echo "Attempt to use precedence for a method $adaptationMethodStr() that hasn't been imported";
						continue;
					}
					unset($methods[$adaptationMethodStr][$name]);
				}
			}
		}

		return $methods;
	}

	private static function getConstant(ClassLike $class, string $name) {
		foreach($class->stmts as $stmt) {
			if ($stmt instanceof Node\Stmt\Const_) {
				foreach($stmt->consts as $const) {
					if ($const->name->name == $name) {
						return [$stmt, $const];
					}
				}
			}
		}
		return null;
	}

	private function constantsAreCompatible(Node\Stmt\ClassConst $parent, Node\Const_ $parentConst, Node\Stmt\ClassConst $child, Node\Const_ $childConst) : bool {
		if ($parent->flags != $child->flags) {
			return false;
		}

		if (!TypeComparer::literalValuesAreEqual($parentConst->value, $childConst->value)) {
			return false;
		}


		return true;
	}

	private function importConstants(ClassLike $class, array $constants, array $outputStatements) {
		/** @var Node\Stmt\ClassConst $constant */
		foreach($constants as $constant) {
			foreach($constant->consts as $const) {
				$existing = self::getConstant($class, $const->name);
				if (!$existing) {
					$outputStatements[] = $constant;
				} else {
					[$existingStmt, $existingConst] = $existing;
					if (!$this->constantsAreCompatible($existingStmt, $existingConst, $constant, $const)) {
						echo "Constant {$const->name} is incompatible with existing constant\n";
					}
				}
			}
		}
		return $outputStatements;
	}

	/**
	 * importMethods
	 *
	 * @param ClassLike $class   Instance of ClassLike
	 * @param array     $methods The array of methods
	 *
	 * @return array
	 */
	private function importMethods(ClassLike $class, array $methods, array $outputStatements) {
		foreach ($methods as $methodName => $methodArr) {
			if (count($methodArr) > 1) {
				echo "[{$class->name}] Too many implementations for $methodName\n";
			}
			foreach ($methodArr as $traitName => $method) {
				if (!$class->getMethod($method->name)) {
					$outputStatements[] = $method;
				}
			}
		}
		return $outputStatements;
	}

	static function findPropProp(Node\Stmt\Property $prop, $name):?Node\Stmt\PropertyProperty {
		foreach($prop->props as $propProp) {
			if ($propProp->name->name == $name) {
				return $propProp;
			}
		}
		return null;
	}

	private static function typesAreIdentical($type1, $type2) {
		$type1 = TypeComparer::normalizeType($type1);
		$type2 = TypeComparer::normalizeType($type2);
		return TypeComparer::typeToString($type1) == TypeComparer::typeToString($type2);
	}

	private function propsAreCompatible(
		Node\Stmt\Property $parent,
		Node\Stmt\PropertyProperty $parentProp,
		Node\Stmt\Property $child,
		Node\Stmt\PropertyProperty $childProp
	) : bool {
		if ($parent->flags != $child->flags) {
			return false;
		}

		if (!self::typesAreIdentical($parent->type, $child->type)) {
			return false;
		}

		if (!TypeComparer::literalValuesAreEqual($childProp->default, $parentProp->default)) {
			return false;
		}
		return true;
	}

	private function importProperties(ClassLike $class, array $properties, array $outputStatements) {
		foreach ($properties as $property) {
			foreach($property->props as $propertyProperty) {
				$existing1 = false;
				foreach($properties as $property2) {
					foreach ($property2->props as $propertyProperty2) {
						// If they're the same name, but different instances.
						if ($propertyProperty->name->name == $propertyProperty2->name->name && $propertyProperty !== $propertyProperty2) {
							$existing1 = true;
							if (!$this->propsAreCompatible($property, $propertyProperty, $property2, $propertyProperty2)) {
								echo "Incompatible property {$propertyProperty->name->name} in two traits being imported into " . ($class->name ?? 'anoynmous class') . "\n";
							}
						}
					}
				}
				if (!$existing1) {
					$existing = $class->getProperty($propertyProperty->name);
					if (!$existing) {
						$outputStatements[] = new Node\Stmt\Property($property->flags, [$propertyProperty], $property->getAttributes(), $property->type, $property->attrGroups);
					} else {
						$existingPropProp = static::findPropProp($existing, $propertyProperty->name->name);
						if (!$this->propsAreCompatible($existing, $existingPropProp, $property, $propertyProperty)) {
							echo "Property {$propertyProperty->name->name} is incompatible with existing property in " . ($class->name ?? "anonymous class") . "\n";
						}
					}
				}
			}
		}
		return $outputStatements;
	}

	function merge($methods, $newMethods) {
		foreach($newMethods as $methodName=>$methodArr) {
			foreach($methodArr as $traitName=>$method) {
				if (!isset($methods[$methodName])) {
					$methods[$methodName] = [$traitName=>$method];
				} else {
					$methods[$methodName][$traitName] = $method;
				}
			}
		}
		return $methods;
	}

	/**
	 * indexTrait
	 *
	 * Different Traits may implement their own copies of the same method name.  That's fine at this point.  Later we will
	 * use adaptations to reduce duplicates down to a single method for each name.
	 *
	 * @param TraitUse $use        Instance of TraitUse
	 *
	 * @return array
	 *
	 * @throws UnknownTraitException
	 */
	private function indexTrait(TraitUse $use) {
		$properties = $methods = $constants = [];
		$newStmts = [];
		$line = $use->getLine();
		foreach ($use->traits as $useTrait) {
			$traitName = strval($useTrait);
			$trait = $this->index->getTrait($traitName);
			$fileName = $this->index->getTraitFile($traitName);

			if (!$trait) {
				throw new \BambooHR\Guardrail\Exceptions\UnknownTraitException($traitName, $use->getLine());
			}

			// Recurse down into any use statements inside of the trait.
			$newStmts = $this->processClassLike($trait);
			[$newProperties, $newMethods, $newConstants] = $this->attachTraitLineNumbers($fileName, $line, $newStmts, $traitName);
			$properties=array_merge($properties, $newProperties);

			$methods = $this->merge($methods, $newMethods);
			$constants = array_merge($constants, $newConstants);
		}
		return [$methods, $properties, $constants];
	}

	private function filterAbstractMethodsWithConcreteVersions($methods):array {
		foreach ($methods as $methodName => $methodArr) {
			if (count($methodArr) > 1) {
				$abstractCount = array_reduce($methodArr, fn($sum,$method) => $sum + ($method->isAbstract() ? 1 : 0), 0);
				$concreteCount = count($methodArr) - $abstractCount;
				if ($abstractCount > 0 && $concreteCount > 0) {
					// If there are both concrete and abstract methods, then discard abstract methods.
					// (We can't actually paste both into a single class.)
					$methods[$methodName] = array_filter($methodArr, function($method) { return !$method->isAbstract(); });
				}
			}
		}
		return $methods;
	}

	public function checkAbstractMethodCompatibility(array $methods):void {
		foreach ($methods as $methodName => $methodArr) {
			$first = reset($methodArr);
			$trait1 = key($methodArr);
			if (count($methodArr) > 1) {
				/** @var Node\Stmt\ClassMethod $first */

				foreach(array_slice($methodArr,1) as $trait2 => $other) {
					/** @var Node\Stmt\ClassMethod $other */
					if ($first->returnsByRef() != $other->returnsByRef()) {
						echo "Method $methodName returns by reference in one implementation but not the other ($trait1 vs $trait2)\n";
					}
					if ($first->isStatic() != $other->isStatic()) {
						echo "Method $methodName is static in one implementation but not the other ($trait1 vs $trait2)\n";
					}
					if ($first->isFinal() != $other->isFinal()) {
						echo "Method $methodName is final in one implementation but not the other ($trait1 vs $trait2)\n";
					}
					if ($first->isPublic() != $other->isPublic()) {
						echo "Method $methodName is public in one implementation but not the other ($trait1 vs $trait2)\n";
					}
					if ($first->isProtected() != $other->isProtected()) {
						echo "Method $methodName is protected in one implementation but not the other ($trait1 vs $trait2)\n";
					}
					if ($first->isPrivate() != $other->isPrivate()) {
						echo "Method $methodName is private in one implementation but not the other ($trait1 vs $trait2)\n";
					}
				}
			}
		}
	}

	public function processClassLike(ClassLike $class):array {
		$additions = [];
		$properties = $methods = $constants = [];

		// First, index all methods
		foreach($class->stmts as $stmt) {
			if ($stmt instanceof Node\Stmt\TraitUse) {
				[$newMethods, $newProperties, $newConstants] = $this->indexTrait($stmt);
				$properties = array_merge($properties, $newProperties);
				$methods = $this->merge($methods, $newMethods);
				$constants = array_merge($constants, $newConstants);
			}
		}
		// Second, having seen all uses clauses for the class, we can now import the methods and properties.
		// Also, while we're at it, make a copy of the all the class statements that aren't "use" statements.
		foreach($class->stmts as $stmt) {
			if ($stmt instanceof Node\Stmt\TraitUse) {
				$methods = $this->resolveAdaptations($stmt->adaptations, $methods);
			} else {
				$additions[] = $stmt;
			}
		}


		$this->checkAbstractMethodCompatibility($methods);
		$methods = $this->filterAbstractMethodsWithConcreteVersions($methods);

		// Finally, import all trait methods, properties, and constants on the end of the $additions list.
		$additions = $this->importMethods($class, $methods, $additions);
		$additions = $this->importProperties($class, $properties, $additions);
		$additions = $this->importConstants($class, $constants, $additions);
		return $additions;
	}
}
