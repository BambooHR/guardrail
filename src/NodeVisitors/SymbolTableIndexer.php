<?php namespace BambooHR\Guardrail\NodeVisitors;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Output\OutputInterface;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\NodeTraverser;
use PhpParser\NodeTraverserInterface;
use BambooHR\Guardrail\Checks\BaseCheck;
use PhpParser\NodeVisitorAbstract;

/**
 * Class SymbolTableIndexer
 *
 * @package BambooHR\Guardrail\NodeVisitors
 */
class SymbolTableIndexer extends NodeVisitorAbstract {

	/**
	 * @var
	 */
	private $index;

	/**
	 * @var array
	 */
	private $classStack = [];

	/**
	 * @var string
	 */
	private $filename = "";

	/** @var OutputInterface  */
	private $output;

	/**
	 * SymbolTableIndexer constructor.
	 *
	 * @param string $index The index
	 */
	public function __construct($index) {
		$this->index = $index;
	}

	/**
	 * setFilename
	 *
	 * @param string $filename The name of the file
	 *
	 * @return void
	 */
	public function setFilename($filename) {
		$this->classStack = [];
		$this->filename = $filename;
	}

	/**
	 * enterNode
	 *
	 * @param Node $node Instance of Node
	 *
	 * @return int|null
	 */
	public function enterNode(Node $node) {
		if ($node instanceof Class_) {
			$name = isset($node->namespacedName) ? $node->namespacedName->toString() : "anonymous class";
			if ($name) {
				$this->index->addClass($name, $node, $this->filename);
				array_push($this->classStack, $node);
			}
		} elseif ($node instanceof Interface_) {
			$name = $node->namespacedName->toString();
			$this->index->addInterface($name, $node, $this->filename);
			array_push($this->classStack, $node);
		} elseif ($node instanceof Function_) {
			$name = $node->namespacedName->toString();
			$this->index->addFunction($name, $node, $this->filename);
		} elseif ($node instanceof Node\Const_) {
			if (count($this->classStack) == 0) {
				$defineName = strval($node->name);
				$this->index->addDefine($defineName, $node, $this->filename);
			}
		} elseif ($node instanceof FuncCall) {
			if ($node->name instanceof Node\Name) {
				$name = strval($node->name);
				if (strcasecmp($name, 'define') == 0 && count($node->args) >= 1) {
					$arg0 = $node->args[0]->value;
					if ($arg0 instanceof Node\Scalar\String_) {
						$defineName = $arg0->value;
						$this->index->addDefine($defineName, $node, $this->filename);
					}
				}
			}
		} elseif ($node instanceof Trait_) {
			$name = $node->namespacedName->toString();
			$this->index->addTrait($name, $node, $this->filename);
			array_push($this->classStack, $node);
		} elseif ($node instanceof Node\Expr) {
			// Expressions don't contain anything we would index.
			return NodeTraverser::DONT_TRAVERSE_CHILDREN;
		}
		return null;
	}

	/**
	 * leaveNode
	 *
	 * @param Node $node Instance of Node
	 *
	 * @return null
	 */
	public function leaveNode(Node $node) {
		if ( ($node instanceof Class_ && isset( $node->namespacedName )) || $node instanceof Interface_ || $node instanceof Trait_) {
			array_pop($this->classStack);
		}
		return null;
	}
}

