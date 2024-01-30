<?php namespace BambooHR\Guardrail\NodeVisitors;

/**
 * Guardrail.  Copyright (c) 2016-2023, BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Output\OutputInterface;
use PhpParser\Builder\ClassConst;
use PhpParser\Builder\Enum_;
use PhpParser\Builder\EnumCase;
use PhpParser\Builder\Param;
use PhpParser\Builder\Property;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\NodeTraverser;
use PhpParser\NodeTraverserInterface;
use BambooHR\Guardrail\Checks\BaseCheck;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use PhpParser\NodeVisitorAbstract;

/**
 * Class SymbolTableIndexer
 *
 * @package BambooHR\Guardrail\NodeVisitors
 */
class SymbolTableIndexer extends NodeVisitorAbstract {

	/**
	 * @var SymbolTable
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
	 * @param SymbolTable $index The index
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
		if ($node instanceof Node\Stmt\Enum_) {
			$name=strval($node->namespacedName);
			$this->addEnumPropsAndMethods($node);
			$this->index->addClass($name, $node, $this->filename);
			array_push($this->classStack, $node);
		} elseif ($node instanceof Class_) {
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
				$defineName = strval($node->namespacedName);
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
		} else if ($node instanceof Node\Expr) {
			// Expressions don't contain anything we would index.
			return NodeTraverser::DONT_TRAVERSE_CHILDREN;
		}
		return null;
	}

	public function addEnumPropsAndMethods(Node\Stmt\Enum_ $enum) {
		$isBacked = !is_null($enum->scalarType);
		$property = new Property("name");
		$property->setType(new Node\Identifier("string"));
		$property->makeReadonly();
		$enum->stmts[]= $property->getNode();
		if ($isBacked) {
			$enum->stmts[] = new Node\Stmt\ClassMethod("values",["returnType"=>"array"]);
			$property = new Property("value");
			$property->makeReadonly();
			$property->setType( $enum->scalarType );
			$enum->stmts[]=$property->getNode();

			$enumName = $enum->namespacedName->toString();
			$param = (new Param("fromValue"))->setType($enum->scalarType);
			$enum->stmts[]=new Node\Stmt\ClassMethod("tryFrom",["returnType" => $enumName, "flags"=>Class_::MODIFIER_STATIC, 'params'=>[$param->getNode()]]);
			$enum->stmts[]=new Node\Stmt\ClassMethod("from", ["returnType" => $enumName, "flags"=>Class_::MODIFIER_STATIC, 'param'=>[$param->getNode()]]);
		}
	}

	/**
	 * leaveNode
	 *
	 * @param Node $node Instance of Node
	 *
	 * @return null
	 */
	public function leaveNode(Node $node) {
		if ( ($node instanceof Class_ && isset( $node->namespacedName )) || $node instanceof Interface_ || $node instanceof Trait_ || $node instanceof Enum_) {
			array_pop($this->classStack);
		}
		return null;
	}
}

