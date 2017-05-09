<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\NodeVisitors;

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
	/** @var  TraitImportingVisitor */
	private $importer;
	private $file;
	private $classStack = [];

	function __construct( SymbolTable $index) {
		$this->importer  = new TraitImporter($index);
	}

	function setFile($name) {
		$this->file=$name;
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
			$traits = $this->importer->resolveTraits($node,$class);
			return $traits;
		}
		return null;
	}
}
