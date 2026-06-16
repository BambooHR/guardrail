<?php

namespace BambooHR\Guardrail\NodeVisitors;

/**
 * Guardrail.  Copyright (c) 2016-2023, BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\EnumCodeAugmenter;
use BambooHR\Guardrail\Output\OutputInterface;
use PhpParser\Builder\Param;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\NodeTraverser;
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

	private array $nodeStack = [];

	/**
	 * @var string
	 */
	private $filename = "";


	/**
	 * SymbolTableIndexer constructor.
	 *
	 * @param SymbolTable $index The index
	 */
	public function __construct($index, private OutputInterface $output) {
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
		$this->nodeStack = [];
		$this->filename = $filename;
	}

	/**
	 * This originally implemented a much more complex check for in the polyfill file for
	 * if(is_callable('your_func')||class_exists||interface_exists||function_exists))
	 * But it was removed because there are too many ways to conditionally include polyfills and a partial implementation
	 * would not be useful.  For now, we're content to know that the declaration was found inside of an if() statement.
	 *
	 * ie: if (PHP_VERSION_NUM<80000) { include "polyfill.php"; }
	 *
	 * @param Function_|Class_|Interface_ $declarationNode
	 * @param string                      $type
	 * @return bool
	 */
	function isInsideConditionalDeclaration(Function_|Class_|Interface_|FuncCall|Enum_ $declarationNode, string $type): bool {
		$found = false;
		foreach ($this->nodeStack as $node) {
			if ($node instanceof Node\Stmt\If_) {
				ForEachNode::run([...$node->stmts], function ($stmt) use (&$found, $declarationNode) {
					if ($stmt === $declarationNode) {
						$found = true;
					}
				});
				if ($found) {
					return true;
				}
			}
		}
		return false;
	}


	public function isInternalClass($name) {
		if (!class_exists($name)) {
			return false;
		}
		try {
			$type = new \ReflectionClass($name);
			return !$type->isUserDefined();
		} catch (\ReflectionException) {
			return false;
		}
	}

	public function isInternalFunction($name) {
		if (!function_exists($name)) {
			return false;
		}
		try {
			$type = new \ReflectionFunction($name);
			return !$type->isUserDefined();
		} catch (\ReflectionException) {
			return false;
		}
	}

	function isInternalDefine($name): bool {
		static $internalDefines = null;
		if ($internalDefines === null) {
			$temp = get_defined_constants(true);
			foreach ($temp as $area => $defines) {
				if ($area != 'user') {
					foreach ($defines as $define => $value) {
						$internalDefines[$define] = true;
					}
				}
			}
		}
		return isset($internalDefines[$name]);
	}


	/**
	 * enterNode
	 *
	 * @param Node $node Instance of Node
	 *
	 * @return int|null
	 */
	public function enterNode(Node $node) {
		$this->nodeStack[] = $node;
		if ($node instanceof Node\Stmt\Enum_) {
			EnumCodeAugmenter::addEnumPropsAndMethods($node);
			$this->handleClass($node);
		} elseif ($node instanceof Class_) {
			$this->handleClass($node);
		} elseif ($node instanceof Interface_) {
			$this->handleInterface($node);
		} elseif ($node instanceof Function_) {
			$this->handleFunction($node);
		} elseif ($node instanceof Node\Const_ && count($this->classStack) == 0) {
			$defineName = strval($node->namespacedName);
			$this->index->addDefine($defineName, $node, $this->filename);
		} elseif (
			$node instanceof FuncCall &&
			$node->name instanceof Node\Name &&
			strcasecmp($node->name->toString(), 'define') == 0 &&
			count($node->args) >= 1
		) {
			$arg0 = $node->args[0]->value;
			if ($arg0 instanceof Node\Scalar\String_) {
				$this->handleDefine($arg0, $node);
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
		if (($node instanceof Class_ && isset($node->namespacedName)) || $node instanceof Interface_ || $node instanceof Trait_ || $node instanceof Enum_) {
			array_pop($this->classStack);
		}
		array_pop($this->nodeStack);
		return null;
	}

	/**
	 * @param Node\Scalar\String_ $arg0
	 * @param FuncCall            $node
	 * @return void
	 */
	public function handleDefine(Node\Scalar\String_ $arg0, FuncCall $node): void {
		$defineName = $arg0->value;
		$existingFile = $this->index->getDefineFile($defineName);
		$defineFile = $existingFile ? $this->index->removeBasePath($existingFile) : "";
		$internal = $this->isInternalDefine($defineName);
		if (!$internal && $defineFile === "") {
			$this->index->addDefine($defineName, $node, $this->filename);
		} else {
			if (!$this->isInsideConditionalDeclaration($node, "define")) {
				// This is a duplicate define
				$this->output->outputVerbose("\nDuplicate define $defineName found in " . $this->filename . " and " . $defineFile . "\n");
			}
		}
	}

	/**
	 * @param Function_ $node
	 * @return void
	 */
	public function handleFunction(Function_ $node): void {
		$name = $node->namespacedName->toString();

		if ($this->isInternalFunction($name)) {
			if (!$this->isInsideConditionalDeclaration($node, "function")) {
				echo "\nAttempt to unconditionally redeclare internal function $name() found in " . $this->filename . "\n";
			}
		} else {
			$existingFile = $this->index->getFunctionFile($name);
			$functionFile = $existingFile ? $this->index->removeBasePath($existingFile) : "";
			if ($functionFile === "") {
				$this->index->addFunction($name, $node, $this->filename);
			} else {
				if (!$this->isInsideConditionalDeclaration($node, "function")) {
					$this->output->outputVerbose("\nDuplicate function $name() found in " . $this->filename . " and " . $functionFile . "\n");
				}
			}
		}
	}

	/**
	 * @param Class_|Enum_ $node
	 * @return void
	 */
	public function handleClass(Class_|Node\Stmt\Enum_ $node): void {
		$name = isset($node->namespacedName) ? $node->namespacedName->toString() : "";
		if ($name) {
			if ($this->isInternalClass($name)) {
				if (!$this->isInsideConditionalDeclaration($node, "class")) {
					$this->output->outputVerbose("\nAttempt to unconditionally redeclare internal class $name found in " . $this->filename . "\n");
				}
			} else {
				$existingFile = $this->index->getClassFile($name);
				$filename = $existingFile ? $this->index->removeBasePath($existingFile) : "";
				if ($filename === "") {
					$this->index->addClass($name, $node, $this->filename);
				} else {
					if (!$this->isInsideConditionalDeclaration($node, "class")) {
						$this->output->outputVerbose("\nDuplicate class $name found in " . $this->filename . " and " . $filename . "\n");
					}
				}
			}
		}
		array_push($this->classStack, $node);
	}

	/**
	 * @param Interface_ $node
	 * @return void
	 */
	public function handleInterface(Interface_ $node): void {
		$name = $node->namespacedName->toString();

		$existingFile = $this->index->getInterfaceFile($name);
		$existing = $existingFile ? $this->index->removeBasePath($existingFile) : "";
		if ($existing) {
			if (!$this->isInsideConditionalDeclaration($node, "interface")) {
				$this->output->outputExtraVerbose("\nDuplicate interface $name found in file $this->filename and " . ($existing ?? "(internal)") . "\n");
			}
		} else {
			$this->index->addInterface($name, $node, $this->filename);
		}
		array_push($this->classStack, $node);
	}
}
