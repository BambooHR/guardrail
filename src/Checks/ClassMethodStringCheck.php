<?php

namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\Util;
use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassLike;


class ClassMethodStringCheck extends BaseCheck {
	/**
	 * @return string[]
	 */
	function getCheckNodeTypes() {
		return [Node\Expr\BinaryOp\Concat::class];
	}

	/**
	 * Finds nodes of the type ClassName::class."@method" and then looks up the method name.
	 * These are commonly used in router method names.  It's not a certainty that the string we find
	 * is a method name, so we emit a different error that can be disabled if the user desires.
	 *
	 * @param string         $fileName -
	 * @param Node           $node     -
	 * @param ClassLike|null $inside   -
	 * @param Scope|null     $scope    -
	 * @return void
	 */
	public function run($fileName, Node $node, ClassLike $inside = null, Scope $scope = null) {
		assert($node instanceof Node\Expr\BinaryOp\Concat);

		// Look for ClassName::class."@method"
		$left = $node->left;
		$right = $node->right;
		if ($left instanceof ClassConstFetch) {
			if ($right instanceof Node\Scalar\String_) {
				if ($left->name == "class" && $left->class instanceof Name) {
					$className = strval($left->class);
					$methodName = $right->value;
					if ($methodName && $methodName[0] == "@") {
						$methodName = substr($methodName, 1);
						$method = Util::findAbstractedMethod($className, $methodName, $this->symbolTable);
						if (!$method) {
							$this->emitError($fileName, $right, ErrorConstants::TYPE_UNKNOWN_METHOD_STRING, "String references a non-existant method ($className@$methodName)");
						}
					}
				}
			}
		}
	}
}