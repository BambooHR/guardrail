<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\NodeVisitors;

use PhpParser\Node;
use PhpParser\NodeVisitor;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Stmt\TraitUseAdaptation;
use BambooHR\Guardrail\DoWhileStatement;
use BambooHR\Guardrail\Exceptions\UnknownTraitException;
use BambooHR\Guardrail\SymbolTable\SymbolTable;

/**
 * Class TraitImportingVisitor
 *
 * This visitor modifies the tree by replacing Use statements in traits/classes with
 * the appropriate methods and properties.
 */
class TraitImportingVisitor implements NodeVisitor {
	/** @var  SymbolTable */
	private $index;
	private $file;
	private $classStack = [];

	function __construct( SymbolTable $index) {
		$this->index=$index;
	}

	function beforeTraverse(array $nodes) {
		return null;
	}

	function setFile($name) {
		$this->file=$name;
	}

	/**
	 * @param TraitUseAdaptation[] $adaptations
	 * @param array                $methods
	 */
	private function resolveAdaptations(array $adaptations, array &$methods) {
		foreach($adaptations as $adaptation) {
			if($adaptation instanceof Node\Stmt\TraitUseAdaptation\Alias) {
				// Alias adaptation renames the alias
				if(!array_key_exists($adaptation->method, $methods)) {
					continue;
				}

				if($adaptation->trait=="") {
					$method = end($methods[$adaptation->method]);
				} else {
					$method = $methods[$adaptation->method][$adaptation->trait];
				}

				$method->name = $adaptation->newName;
				if(property_exists($method, 'type')) {
					$method->type = $method->type & ~( Class_::MODIFIER_PRIVATE | Class_::MODIFIER_PROTECTED | Class_::MODIFIER_PUBLIC) | $adaptation->newModifier;
					$method->setAttribute("ImportedFromTrait", strval($adaptation->trait));

					// Unset it from the old name.
					unset($methods[$adaptation->method][$adaptation->trait]);
					// Add it with the new name.
					$methods[$adaptation->newName][$adaptation->trait]=$method;
				}
			} else if($adaptation instanceof Node\Stmt\TraitUseAdaptation\Precedence) {
				// Instance of adaptation ignores the method from a list of traits.
				foreach($adaptation->insteadof as $name) {
					unset($methods[$adaptation->method][$name]);
				}
			}
		}
	}

	private function importMethods(ClassLike $class, array $methods) {
		$stmts = [];
		foreach($methods as $methodName=>$methodArr) {
			if(count($methodArr)>1) {
				echo "Too many implementations for $methodName\n";
			}
			foreach($methodArr as $traitName=>$method) {
				if(!$class->getMethod($method->name)) {
					$stmts[] = $method;
				}
			}
		}
		return $stmts;
	}

	/**
	 * Different Traits may implement their own copies of the same method name.  That's fine at this point.  Later we will
	 * use adaptations to reduce duplicates down to a single method for each name.
	 * @param TraitUse $use
	 * @param array    $methods
	 * @param array    $properties
	 * @throws UnknownTraitException
	 */
	private function indexTrait(TraitUse $use, array &$methods, array &$properties) {
		foreach ($use->traits as $useTrait) {
			$traitName = strval($useTrait);
			$trait = $this->index->getTrait($traitName);
			if (!$trait) {
				throw new \BambooHR\Guardrail\Exceptions\UnknownTraitException($traitName, $this->file, $use->getLine());
			}
			foreach ($trait->stmts as $stmt) {
				if ($stmt instanceof Node\Stmt\Property) {
					foreach($stmt->props as $prop) {
						// Make a deep copy of the node
						$properties[$prop->name] = unserialize( serialize( $prop ) );
					}
				} else if($stmt instanceof Node\Stmt\ClassMethod) {
					// Make a deep copy of the node
					$methods[$stmt->name][$traitName] = unserialize( serialize( $stmt ) );
				}
			}
		}
	}

	/**
	 * Note: we don't directly recurse down into traits here to resolve their traits.  Instead, we allow that to happen
	 * indirectly when we call $this->index->getTrait().
	 *
	 * @param Node\Stmt\TraitUse  $use
	 * @param Node\Stmt\ClassLike $class
	 * @throws UnknownTraitException
	 */
	function resolveTraits(Node\Stmt\TraitUse $use, Node\Stmt\ClassLike $class) {
		$methods = [];
		$properties = [];

		$this->indexTrait($use, $methods, $properties);
		$this->resolveAdaptations($use->adaptations, $methods );

		return array_merge( array_values($properties), $this->importMethods($class, $methods));
	}


	function enterNode(Node $node) {
		if($node instanceof Class_ || $node instanceof Trait_) {
			array_push($this->classStack, $node);
		}
		return null;
	}

	function leaveNode(Node $node) {
		if($node instanceof Class_ || $node instanceof Trait_) {
			array_pop($this->classStack);
		} else if($node instanceof Node\Stmt\TraitUse) {
			$class=end($this->classStack);
			assert($class);
			$traits = $this->resolveTraits($node,$class);
			return $traits;
		}
		// The default do/while visits the condition before the statement list.
		// This causes undefined variable errors.  We correct it by replacing it with
		// a subclass that in the order we need.
		if($node instanceOf Node\Stmt\Do_) {
			return DoWhileStatement::fromDo_($node);
		}
		return null;
	}

	function afterTraverse(array $nodes) {
		return null;
	}
}
