<?php namespace BambooHR\Guardrail\Checks;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\NodeVisitors\ForEachNode;
use PhpParser\Node;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\ClassLike;
use BambooHR\Guardrail\Scope;
use PhpParser\NodeTraverser;

/**
 * Class CatchCheck
 *
 * @package BambooHR\Guardrail\Checks
 */
class CatchCheck extends BaseCheck {

	/**
	 * getCheckNodeTypes
	 *
	 * @return string[]
	 */
	public function getCheckNodeTypes() {
		return [Catch_::class];
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
		if ($node instanceof Catch_) {
			foreach ($node->types as $nameOb) {
				$name = strval($nameOb);
				if ($this->symbolTable->ignoreType($name)) {
					// exception is in the ignore list... but if the error constant is turned on, we should emit this error
					if ('exception' == $node->var) {
						/* Detect a throw at any depth in the catch() subtree.  (Ignoring nested try/catch blocks).
						   We trust that if they throw anything, they made a conscious decision about how the
						   exception needed to bubble up. */
						if (!$this->doesCatchRethrow($node)) {
							$this->emitError($fileName, $node, ErrorConstants::TYPE_EXCEPTION_BASE, "Catching the base Exception class without subsequently throwing may be too broad");
						}
					}
				} else if (!$this->symbolTable->isDefinedClass($name)) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_UNKNOWN_CLASS, "Attempt to catch unknown type: $name");
				}
			}
		}
	}

	/**
	 * @param Node $node
	 * @return bool
	 */
	protected function doesCatchRethrow(Catch_ $node) {
		$throws = false;
		ForEachNode::run($node->stmts, function (Node $node) use (&$throws) {
			if ($node instanceof Node\Stmt\Throw_) {
				$throws = true;
				return NodeTraverser::STOP_TRAVERSAL;
			} else if ($node instanceof Node\Stmt\TryCatch) {
				// We don't care about nested try/catches
				return NodeTraverser::DONT_TRAVERSE_CHILDREN;
			}
			return null;
		});
		return $throws;
	}
}