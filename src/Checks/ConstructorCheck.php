<?php
/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\Checks;


use BambooHR\Guardrail\Abstractions\Class_;
use BambooHR\Guardrail\NodeVisitors\ForEachNode;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\Util;
use PhpParser\Node;

class ConstructorCheck extends BaseCheck {
	function getCheckNodeTypes() {
		return [\PhpParser\Node\Stmt\ClassMethod::class];
	}

	static function containsConstructorCall(array $stmts = null) {
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

	function run($fileName, $node, Node\Stmt\ClassLike $inside = null, Scope $scope = null) {
		/** var \PhpParser\Node\Stmt\ClassMethod $node */
		if (strcasecmp($node->name,"__construct")==0 &&
			$inside instanceof Node\Stmt\Class_ &&
			$inside->extends
		) {
			$ob = Util::findAbstractedMethod($inside->extends, "__construct", $this->symbolTable);
			if ($ob &&
				!$ob->isAbstract() &&
				!self::containsConstructorCall($node->stmts)
			) {
				$this->emitError($fileName, $node, BaseCheck::TYPE_MISSING_CONSTRUCT, "Class " . $inside->name . " overrides __construct, but does not call parent constructor");
			}
		}
	}
}