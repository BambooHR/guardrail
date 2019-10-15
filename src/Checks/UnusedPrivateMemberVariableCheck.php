<?php namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\NodeVisitors\ForEachNode;
use BambooHR\Guardrail\NodeVisitors\PropertyUsageVisitor;
use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

/**
 * Class UnusedPrivateMemberVariableCheck
 * @package BambooHR\Guardrail\Checks
 */
class UnusedPrivateMemberVariableCheck extends BaseCheck {
	/** @var NodeTraverser */
	private $traverser;

	/** @var PropertyUsageVisitor  */
	private $usedPropertyVisitor;

	/**
	 * @param SymbolTable     $symbolTable The symbol table to check
	 * @param OutputInterface $doc         The interface to output to
	 */
	function __construct(SymbolTable $symbolTable, OutputInterface $doc) {
		parent::__construct($symbolTable, $doc);

		$this->usedPropertyVisitor = new PropertyUsageVisitor();
		$this->traverser = new NodeTraverser();
		$this->traverser->addVisitor( $this->usedPropertyVisitor );
	}

	/**
	 * getCheckNodeTypes
	 *
	 * @return string[]
	 */
	function getCheckNodeTypes() {
		return [Class_::class];
	}

	/**
	 * run
	 *
	 * @param string         $fileName The name of the file we are parsing
	 * @param Node           $node     Instance of the Node
	 * @param ClassLike|null $inside   Instance of the ClassLike (the class we are parsing) [optional]
	 * @param Scope|null     $scope    Instance of the Scope (all variables in the current state) [optional]
	 *
	 * @return void
	 */
	public function run($fileName, Node $node, ClassLike $inside = null, Scope $scope = null) {
		if ($node instanceof Class_) {
			$props = [];

			// Catalog all private properties
			foreach ($node->stmts as $stmt) {
				if ($stmt instanceof Property && $stmt->flags == Class_::MODIFIER_PRIVATE) {
					foreach ($stmt->props as $prop) {
						$props[$prop->name->name] = $prop;
					}
				}
			}

			// Catalog which properties are actually referenced
			$usedVariables = $this->checkInside($node);

			if ($this->usedPropertyVisitor->detectedDynamicScripting()) {
				// All bets are off if there was dynamic scripting.
				return;
			}

			// Output an error for each unused private variable.
			foreach ($props as $memberVariable => $propNode) {
				if (!array_key_exists($memberVariable, $usedVariables)) {
					$this->emitError($fileName, $propNode, ErrorConstants::TYPE_UNUSED_PROPERTY, "Unused private variable \$$memberVariable detected");
				}
			}
		}
	}

	/**
	 * checkInside
	 *
	 * @param ClassLike|null $inside Instance of the ClassLike (the class we are parsing)
	 *
	 * @return array
	 */
	protected function checkInside(ClassLike $inside) {
		$this->usedPropertyVisitor->reset();
		foreach ($inside->stmts as $statement) {
			// we will ignore constructors for the purposes of usage
			if ($statement instanceof Node\Stmt\ClassMethod && $statement->name !== '__construct' && $statement->stmts) {
				$this->traverser->traverse($statement->stmts);
			}
		}
		return $this->usedPropertyVisitor->getUsedProperties();
	}
}
