<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\NodeVisitors;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeVisitor;
use BambooHR\Guardrail\Checks\BaseCheck;

class SymbolTableIndexer implements NodeVisitor {
	private $index;
	private $classStack = [];
	private $filename = "";
	/** @var \BambooHR\Guardrail\Output\OutputInterface  */
	private $output;

	function __construct($index, \BambooHR\Guardrail\Output\OutputInterface $output) {
		$this->index=$index;
		$this->output=$output;
	}
	function beforeTraverse(array $nodes) {
		return null;
	}

	function setFilename($filename) {
		$this->classStack = [];
		$this->filename=$filename;
	}

	function enterNode(Node $node) {
		switch(get_class($node)) {
			case Class_::class:
				$name=$node->namespacedName ? $node->namespacedName->toString() : "anonymous class";
				if($name) {
					$file = $this->index->getClassFile($name);
					if ($file) {
						$this->output->emitError(__CLASS__, $this->filename, $node->getLine(), BaseCheck::TYPE_PARSE_ERROR, "Class $name already exists in $file.");
					} else {
						$this->index->addClass($name, $node, $this->filename);
					}
					array_push($this->classStack, $node);
				}
				break;
			case Interface_::class:
				$name=$node->namespacedName->toString();
				$this->index->addInterface($name, $node, $this->filename);
				array_push($this->classStack, $node);
				break;
			case Function_::class:
				$name=$node->namespacedName->toString();
				$this->index->addFunction($name, $node, $this->filename);
				break;
			case \PhpParser\Node\Const_::class:

				if(count($this->classStack)==0) {
					$defineName = strval($node->name);
					$this->index->addDefine($defineName, $node, $this->filename);
				}
				break;
			case FuncCall::class:
				if($node->name instanceof Node\Name) {
					$name = strval($node->name);
					if (strcasecmp($name, 'define') == 0 && count($node->args) >= 1 && $node->args[0]->value instanceof Node\Scalar\String_) {
						$defineName = $node->args[0]->value->value;
						$this->index->addDefine($defineName, $node, $this->filename);
					}
				}
				break;
			case Trait_::class:
				$name=$node->namespacedName->toString();
				$this->index->addTrait($name, $node, $this->filename);
				array_push($this->classStack, $node);
				break;
		}
		if($node instanceof ClassMethod && property_exists($node,'namespacedName') && count($this->classStack)>0) {
			$classNode=$this->classStack[count($this->classStack)-1];
			$className=$classNode->namespacedName->toString();
			$this->index->addMethod($className, $node->name, $node);
		}
		return null;
	}

	function leaveNode(Node $node) {
		if( ($node instanceof Class_ && $node->namespacedName) || $node instanceof Interface_ || $node instanceof Trait_) {
			array_pop($this->classStack);
		}
		return null;
	}

	function afterTraverse(array $nodes) { 
		return null;
	}
}

?>
