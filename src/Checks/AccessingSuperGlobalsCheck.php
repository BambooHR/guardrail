<?php namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Scope;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Global_;

/**
 * Class AccessingSuperGlobalsCheck
 *
 * @package BambooHR\Guardrail\Checks
 */
class AccessingSuperGlobalsCheck extends BaseCheck {

	/**
	 * getCheckNodeTypes
	 *
	 * @return array
	 */
	public function getCheckNodeTypes() {
		return [Global_::class, Variable::class];
	}

	/**
	 * run
	 *
	 * @param string                   $fileName The name of the file being parsed
	 * @param Node                     $node     Reference to the object in the AST
	 * @param Node\Stmt\ClassLike|null $inside   Instance of the ClassLike (the class we are in)
	 * @param Scope|null               $scope    Instance of the Scope (all variables in current state)
	 *
	 * @return void
	 */
	public function run($fileName, Node $node, ?Node\Stmt\ClassLike $inside = null, ?Scope $scope = null) {
		if ($node instanceof Global_) {
			$this->checkForGlobal($fileName, $node);
		}

		// references to global $foo
		if ($node instanceof Variable) {
			$this->checkForVariable($fileName, $node);
		}
	}

	/**
	 * checkForGlobal
	 *
	 * @param string  $fileName The name of the file we are parsing
	 * @param Global_ $node     Instance of the Node
	 *
	 * @return void
	 */
	protected function checkForGlobal($fileName, Global_ $node) {
		foreach ($node->vars as $globalName) {
			$emit = true;
			if ($globalName instanceof Variable) {
				if (is_string($globalName->name)) {
					// global $some, $More, $than, $one;
					$this->emitError($fileName, $node, ErrorConstants::TYPE_GLOBAL_STRING_ACCESSED, "Found global " . $globalName->name);
					$emit = false;
				}
			}
			if ($emit) {
				$this->emitError($fileName, $node, ErrorConstants::TYPE_GLOBAL_EXPRESSION_ACCESSED, "Found global expression");
			}
		}
	}

	/**
	 * checkForVariable
	 *
	 * @param string   $fileName The name of the file we are parsing
	 * @param Variable $node     Instance of the Node
	 *
	 * @return void
	 */
	protected function checkForVariable($fileName, Variable $node) {
		if (is_string($node->name) && 'GLOBALS' == $node->name) { // test case of GLOBALS
			$this->emitError($fileName, $node, ErrorConstants::TYPE_GLOBAL_EXPRESSION_ACCESSED, "Found global expression");
		}
	}
}