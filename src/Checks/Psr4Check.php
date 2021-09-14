<?php

namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\Scope;
use PhpParser\Node;

class Psr4Check extends BaseCheck {
	/**
	 * @var array
	 */
	private $psrRoots;

	/**
	 * Psr4Check constructor.
	 *
	 * @param SymbolTable     $symbolTable Instance of the SymbolTable
	 * @param OutputInterface $doc         Instance of the OutputInterface
	 * @param array           $psrRoots
	 */
	public function __construct(SymbolTable $symbolTable, OutputInterface $doc, array $psrRoots) {
		parent::__construct($symbolTable, $doc);
		$this->psrRoots = $psrRoots;
	}


	/**
	 * @return string[]
	 */
	function getCheckNodeTypes(): array {
		return [Node\Stmt\Class_::class, Node\Stmt\Interface_::class, Node\Stmt\Trait_::class];
	}

	/**
	 * @param Node\Name|null $name The node to grab the class/trait/interface name from.
	 * @return string
	 */
	private function getPsr4Path(Node\Name $name = null) {
		$name = $name ? implode('/', $name->parts) . ".php" : "";
		foreach ($this->psrRoots as $root => $path) {
			if (strpos($name, $root) === 0) {
				return $path . substr($name, strlen($root));
			}
		}
		return "";
	}

	/**
	 * @param string                   $fileName Current filename
	 * @param Node                     $node Current node
	 * @param Node\Stmt\ClassLike|null $inside Current class
	 * @param Scope|null               $scope Any relevant scope
	 * @return void
	 * @guardrail-ignore Standard.Unknown.Property
	 */
	function run(string $fileName, Node $node, Node\Stmt\ClassLike $inside = null, Scope $scope = null) {
		$name = "";
		$fullName = "";
		if ($node instanceof Node\Stmt\Class_) {
			if (isset($node->namespacedName)) {
				$fullName = $this->getPsr4Path($node->namespacedName);
				$name = $node->name;
			}
		} else if ($node instanceof Node\Stmt\Interface_) {
			if (isset($node->namespacedName)) {
				$fullName = $this->getPsr4Path($node->namespacedName);
				$name = $node->name;
			}
		} else if ($node instanceof Node\Stmt\Trait_) {
			if (isset($node->namespacedName)) {
				$fullName = $this->getPsr4Path($node->namespacedName);
				$name = $node->name;
			}
		}

		// All classes with a name, must follow PSR-4 naming.
		// (Anonymous classes obviously don't need to be in their own file.)
		if ($fullName != "" && (strpos($fullName, "/") === false || substr($fileName, -strlen($fullName)) != $fullName)) {
			$this->emitError($fileName, $node, ErrorConstants::TYPE_PSR4, "Class $name is not namespaced as a PSR-4 class");
		}
	}
}