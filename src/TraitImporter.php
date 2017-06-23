<?php namespace BambooHR\Guardrail;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

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
				// Alias adaptation renames the alias
				if (!array_key_exists($adaptation->method, $methods)) {
					echo "Unable to resolve method name : ".$adaptation->method." in ".$adaptation->trait."\n";
					continue;
				}

				if ($adaptation->trait == "") {
					$method = end($methods[$adaptation->method]);
				} else {
					$method = $methods[$adaptation->method][$adaptation->trait];
				}

				if(!$method) {
					echo "Unable to find method ".$adaptation->method." in ".$adaptation->trait."\n";
					continue;
				}

				if (null != $adaptation->newName) {
					$method->name = $adaptation->newName;
					// Unset it from the old name.
					unset($methods[$adaptation->method][$adaptation->trait]);

					// Add it with the new name.
					$methods[$adaptation->newName][$adaptation->trait] = $method;
				}
				if (property_exists($method, 'type')) {
					if(null != $adaptation->newModifier) {
						$method->type = $method->type & ~(Class_::MODIFIER_PRIVATE | Class_::MODIFIER_PROTECTED | Class_::MODIFIER_PUBLIC) | $adaptation->newModifier;
					}
					$method->setAttribute("ImportedFromTrait", strval($adaptation->trait));
				}
			} else if ($adaptation instanceof Node\Stmt\TraitUseAdaptation\Precedence) {
				// Instance of adaptation ignores the method from a list of traits.
				if(!isset($methods[$adaptation->method][$adaptation->trait])) {
					echo "Unable to find ".$adaptation->trait."::".$adaptation->method."\n";
				}
				$method=$methods[$adaptation->method][$adaptation->trait];
				$method->setAttribute("ImportedFromTrait", strval($adaptation->trait));
				foreach ($adaptation->insteadof as $name) {
					if(!$methods[$adaptation->method][$name]) {
						echo "Unable to complete instead of $name::".$adaptation->method.".  It doesn't exist.\n";
					} else {
						unset($methods[$adaptation->method][$name]);
					}
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
				echo "Too many implementations for $methodName\n";
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
	 * @param TraitUse $use        Instance of TraitUse
	 * @param array    $methods    The array of methods
	 * @param array    $properties The array of properties
	 *
	 * @return void
	 *
	 * @throws UnknownTraitException
	 */
	private function indexTrait(TraitUse $use, array &$methods, array &$properties) {
		foreach ($use->traits as $useTrait) {
			$traitName = strval($useTrait);
			$trait = $this->index->getTrait($traitName);
			if (!$trait) {
				throw new \BambooHR\Guardrail\Exceptions\UnknownTraitException($traitName, $use->getLine());
			}
			foreach ($trait->stmts as $stmt) {
				if ($stmt instanceof Node\Stmt\Property) {
					foreach ($stmt->props as $prop) {
						// Make a deep copy of the node
						$propList = new Node\Stmt\Property($stmt->type, [unserialize( serialize( $prop ) )]);
						$propList->props[0]->setAttribute("ImportedFromTrait", strval($traitName));
						$propList->setLine( $use->getLine() );
						$properties[] = $propList;
					}
				} else if ($stmt instanceof Node\Stmt\ClassMethod) {
					// Make a deep copy of the node
					if(isset($methods[$stmt->name][$traitName])) {
						// Same method name declared twice in a single trait.
					} else {
						$methods[$stmt->name][$traitName] = unserialize(serialize($stmt));
					}
				}
			}
		}
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
		$methods = [];
		$properties = [];

		$this->indexTrait($use, $methods, $properties);
		$this->resolveAdaptations($use->adaptations, $methods );

		return array_merge( $properties, $this->importMethods($class, $methods));
	}

	/**
	 * resolveClassTraits
	 *
	 * @param ClassLike $class Instance of ClassLike
	 *
	 * @return void
	 */
	public function resolveClassTraits(ClassLike $class) {
		$replacements = [];
		foreach ($class->stmts as $index => $stmt) {
			if ($stmt instanceof Node\Stmt\TraitUse) {
				unset($class->stmts[$index]);
				$replacements[] = $this->resolveTraits($stmt, $class);
			}
		}
		$class->stmts = array_merge( $class->stmts, $replacements );
	}
}
