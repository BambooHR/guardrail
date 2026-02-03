<?php

namespace BambooHR\Guardrail;

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
	public function __construct(SymbolTable $index) {
		$this->index = $index;
	}

	/**
	 * resolveAdaptations
	 *
	 * @param TraitUseAdaptation[] $adaptations Array of Instances of TraitUseAdaptation
	 * @param array                $methods     The list of methods
	 *
	 * @return void
	 */
	private function resolveAdaptations(array $adaptations, array &$methods) {
		foreach ($adaptations as $adaptation) {
			if ($adaptation instanceof Node\Stmt\TraitUseAdaptation\Alias) {
				$adaptationMethodStr = strval($adaptation->method);
				// Alias adaptation renames the alias
				if (!array_key_exists($adaptationMethodStr, $methods)) {
					continue;
				}

				/** @var Node\Stmt\ClassMethod $method */
				if (strval($adaptation->trait) == "") {
					$method = end($methods[$adaptationMethodStr]);
				} else {
					$method = $methods[$adaptationMethodStr][strval($adaptation->trait)];
				}

				if ($adaptation->newModifier != null) {
					$method->flags = $method->flags & ~(Class_::MODIFIER_PRIVATE | Class_::MODIFIER_PROTECTED | Class_::MODIFIER_PUBLIC) | $adaptation->newModifier;
				}

				if ($adaptation->newName != null) {
					$method->name = $adaptation->newName;

					// Unset it from the old name.
					unset($methods[$adaptationMethodStr][strval($adaptation->trait)]);
					// Add it with the new name.
					$methods[strval($adaptation->newName)][strval($adaptation->trait)] = $method;
				}
			} else if ($adaptation instanceof Node\Stmt\TraitUseAdaptation\Precedence) {
				// Instance of adaptation ignores the method from a list of traits.
				foreach ($adaptation->insteadof as $name) {
					unset($methods[strval($adaptation->method)][$name]);
				}
			}
		}
	}

	/**
	 * importMethods
	 *
	 * @param ClassLike $class   Instance of ClassLike
	 * @param array     $methods The array of methods
	 *
	 * @return array
	 */
	private function importMethods(ClassLike $class, array $methods) {
		$stmts = [];
		foreach ($methods as $methodName => $methodArr) {
			if (count($methodArr) > 1) {
				echo "[{$class->name}] Too many implementations for $methodName\n";
			}
			foreach ($methodArr as $traitName => $method) {
				if (!$class->getMethod($method->name)) {
					$stmts[] = $method;
				}
			}
		}
		return $stmts;
	}

	/**
	 * indexTrait
	 *
	 * Different Traits may implement their own copies of the same method name.  That's fine at this point.  Later we will
	 * use adaptations to reduce duplicates down to a single method for each name.
	 *
	 * @param TraitUse $use Instance of TraitUse
	 *
	 * @return array
	 *
	 * @throws UnknownTraitException
	 */
	private function indexTrait(TraitUse $use) {
		$methods = [];
		$properties = [];
		foreach ($use->traits as $useTrait) {
			$traitName = strval($useTrait);
			$trait = $this->index->getTrait($traitName);
			$line = $use->getLine();

			if ($trait) {
				$imports = [];
				// Recurse down into any use statements inside of the trait.
				foreach ($trait->stmts as $index => $stmt) {
					if ($stmt instanceof TraitUse) {
						$imports[] = $stmt;
						unset($trait->stmts[$index]);
					}
				}
				foreach ($imports as $stmt) {
					$newStatements = $this->resolveTraits($stmt, $trait);
					if ($newStatements) {
						$trait->stmts = array_merge($trait->stmts, $newStatements);
					}
				}
				$trait->stmts = array_values($trait->stmts);
			}

			if (!$trait) {
				throw new \BambooHR\Guardrail\Exceptions\UnknownTraitException($traitName, $use->getLine());
			}
			$fileName = $this->index->getTraitFile($traitName);
			$apply = function (Node $node) use ($fileName, $line) {
				$node->setAttribute('importedFromTrait', $fileName);
				$node->setAttribute('importedOnLine', $line);
			};
			foreach ($trait->stmts as $stmt) {
				if ($stmt instanceof Node\Stmt\Property) {
					$props = unserialize(serialize($stmt));
					ForEachNode::run([$props], $apply);
					$properties[] = $props;
				} else if ($stmt instanceof Node\Stmt\ClassMethod) {
					// Make a deep copy of the node
					$method = unserialize(serialize($stmt));
					ForEachNode::run([$method], $apply);
					$methods[strval($stmt->name)][$traitName] = $method;
				}
			}
		}
		return [$methods, $properties];
	}

	/**
	 * resolveTraits
	 *
	 * Note: we don't directly recurse down into traits here to resolve their traits.  Instead, we allow that to happen
	 * indirectly when we call $this->index->getTrait().
	 *
	 * @param TraitUse  $use   Instance of TraitUse
	 * @param ClassLike $class Instance of ClassLike
	 *
	 * @return array
	 *
	 * @throws UnknownTraitException
	 */
	public function resolveTraits(TraitUse $use, ClassLike $class) {
		[$methods, $properties] = $this->indexTrait($use);
		$this->resolveAdaptations($use->adaptations, $methods);
		return array_merge(array_values($properties), $this->importMethods($class, $methods));
	}
}
