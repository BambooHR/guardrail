<?php namespace BambooHR\Guardrail\Checks;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Scope;
use PhpParser\Node;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassLike;

/**
 * Class InstanceOfCheck
 *
 * @package BambooHR\Guardrail\Checks
 */
class InstanceOfCheck extends BaseCheck {

	/**
	 * getCheckNodeTypes
	 *
	 * @return array
	 */
	public function getCheckNodeTypes() {
		return [Instanceof_::class];
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
	public function run($fileName, Node $node, ?ClassLike $inside=null, ?Scope $scope=null) {
		if ($node instanceof Instanceof_) {
			if ($node->class instanceof Name) {
				$name = $node->class->toString();
				if (strcasecmp($name, "self") != 0 && strcasecmp($name, "static") != 0 && !$this->symbolTable->ignoreType($name)) {
					if (!$this->symbolTable->getAbstractedClass($name)) {
						$this->emitError($fileName, $node, ErrorConstants::TYPE_UNKNOWN_CLASS, "Instance of references unknown class $name");
					}
				}
			}
		}
	}
}
