<?php namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Scope;
use PhpParser\Node;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassLike;


/**
 * Class ClassStoredAsVariableCheck
 * @package BambooHR\Guardrail\Checks
 */
class ClassStoredAsVariableCheck extends BaseCheck {

	/**
	 * getCheckNodeTypes
	 *
	 * @return string[]
	 */
	function getCheckNodeTypes(): array {
		return [String_::class];
	}

	/**
	 * run
	 *
	 * @param string         $fileName The name of the file we are parsing
	 * @param Node           $node Instance of the Node
	 * @param ClassLike|null $inside Instance of the ClassLike (the class we are parsing) [optional]
	 * @param Scope|null     $scope Instance of the Scope (all variables in the current state) [optional]
	 *
	 * @return void
	 */
	public function run(string $fileName, Node $node, ClassLike $inside = null, Scope $scope = null) {
		// if it's a string and a valid PHP class name (including the slashes for namespaced classes)
		if ($node instanceof String_ && preg_match('/^[a-zA-Z_\\x7f-\\xff][a-zA-Z0-9_\\x7f-\\xff]*$/', $node->value)) {
			// full match
			if ($this->symbolTable->isDefinedClass($node->value)) {
				$this->emitError($fileName, $node, ErrorConstants::TYPE_CLASS_STORED_VARIABLE, "Class used in variable. Please use {CLASS_NAME}::class instead.");
			} elseif ($this->symbolTable->classExistsAnyNamespace($node->value)) {
				$this->emitError($fileName, $node, ErrorConstants::TYPE_CLASS_STORED_VARIABLE, "Class used in variable. Please use {CLASS_NAME}::class instead.");
			}
		}
	}

}