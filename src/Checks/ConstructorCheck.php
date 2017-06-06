<?php namespace BambooHR\Guardrail\Checks;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Abstractions\Class_;
use BambooHR\Guardrail\NodeVisitors\ForEachNode;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\Util;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;

/**
 * Class ConstructorCheck
 *
 * @package BambooHR\Guardrail\Checks
 */
class ConstructorCheck extends BaseCheck {

	/**
	 * getCheckNodeTypes
	 *
	 * @return array
	 */
	public function getCheckNodeTypes() {
		return [\PhpParser\Node\Stmt\ClassMethod::class];
	}

	/**
	 * containsConstructorCall
	 *
	 * @param array|null $stmts The statements to check
	 *
	 * @return bool
	 */
	static public function containsConstructorCall(array $stmts = null) {
		$found = false;
		ForEachNode::run($stmts, function (Node $node) use (&$found) {
			if ($node instanceof Node\Expr\StaticCall &&
				strcasecmp($node->name, "__construct") == 0 &&
				$node->class instanceof Node\Name &&
				strcasecmp(strval($node->class), "parent") == 0
			) {
				$found = true;
			}
		});
		return $found;
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
		/** var \PhpParser\Node\Stmt\ClassMethod $node */
		if (strcasecmp($node->name, "__construct") == 0 &&
			$inside instanceof Class_ &&
			$inside->extends
		) {
			$ob = Util::findAbstractedMethod($inside->extends, "__construct", $this->symbolTable);
			if ($ob &&
				!$ob->isAbstract() &&
				!self::containsConstructorCall($node->stmts)
			) {
				$this->emitError($fileName, $node, ErrorConstants::TYPE_MISSING_CONSTRUCT, "Class " . $inside->name . " overrides __construct, but does not call parent constructor");
			}
		}
	}
}