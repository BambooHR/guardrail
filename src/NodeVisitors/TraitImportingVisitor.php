<?php namespace BambooHR\Guardrail\NodeVisitors;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Trait_;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TraitImporter;
use PhpParser\NodeVisitorAbstract;

/**
 * Class TraitImportingVisitor
 *
 * This visitor modifies the tree by replacing Use statements in traits/classes with
 * the appropriate methods and properties.
 */
class TraitImportingVisitor extends NodeVisitorAbstract {

	/** @var TraitImporter */
	private $importer;

	/**
	 * @var string
	 */
	private $file;

	/**
	 * @var array
	 */
	private $classStack = [];

	/**
	 * TraitImportingVisitor constructor.
	 *
	 * @param SymbolTable $index Instance of SymbolTable
	 */
	public function __construct( SymbolTable $index) {
		$this->importer  = new TraitImporter($index);
	}

	/**
	 * setFile
	 *
	 * @param string $name The filename
	 *
	 * @return void
	 */
	public function setFile($name) {
		$this->file = $name;
	}

	/**
	 * enterNode
	 *
	 * @param Node $node Instance of Node
	 *
	 * @return null
	 */
	public function enterNode(Node $node) {
		if ($node instanceof Class_ || $node instanceof Trait_) {
			array_push($this->classStack, $node);
		}
		return null;
	}

	/**
	 * leaveNode
	 *
	 * @param Node $node Instance of Node
	 *
	 * @return array|null
	 */
	public function leaveNode(Node $node) {
		if ($node instanceof Class_ || $node instanceof Trait_) {
			array_pop($this->classStack);
		} else if ($node instanceof Node\Stmt\TraitUse) {

			$class = end($this->classStack);
			assert($class);
			$traits = $this->importer->resolveTraits($node, $class);
			return $traits;
		}
		return null;
	}
}
