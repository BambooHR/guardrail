<?php namespace BambooHR\Guardrail\NodeVisitors;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Enum_;
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
	 * TraitImportingVisitor constructor.
	 *
	 * @param SymbolTable $index Instance of SymbolTable
	 */
	public function __construct( SymbolTable $index) {
		$this->importer  = new TraitImporter($index);
	}

	/**
	 * leaveNode
	 *
	 * @param Node $node Instance of Node
	 *
	 * @return int|null|array
	 */
	public function leaveNode(Node $node) {
		if ($node instanceof Class_ || $node instanceof Trait_ || $node instanceof Enum_) {
			$node->stmts = $this->importer->processClassLike($node);
		}
		return null;
	}
}
