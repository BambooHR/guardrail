<?php namespace BambooHR\Guardrail\Checks;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassLike;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\Util;

/**
 * Class InstantiationCheck
 *
 * @package BambooHR\Guardrail\Checks
 */
class InstantiationCheck extends BaseCheck {

	/**
	 * getCheckNodeTypes
	 *
	 * @return array
	 */
	public function getCheckNodeTypes() {
		return [New_::class];
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
	public function run($fileName, Node $node, ClassLike $inside=null, Scope $scope=null) {
		if ($node instanceof New_) {
			if ($node->class instanceof Name) {
				$name = $node->class->toString();
				if (strcasecmp($name, "self") != 0 && strcasecmp($name, "static") != 0 && !$this->symbolTable->ignoreType($name)) {
					$this->incTests();
					$class = $this->symbolTable->getAbstractedClass($name);
					if (!$class) {
						$this->emitError($fileName, $node, ErrorConstants::TYPE_UNKNOWN_CLASS, "Attempt to instantiate unknown class $name");
						return;
					}
					if ($class->isDeclaredAbstract()) {
						$this->emitError($fileName, $node, ErrorConstants::TYPE_SIGNATURE_TYPE, "Attempt to instantiate abstract class $name");
						return;
					}

					$method = Util::findAbstractedMethod($name, "__construct", $this->symbolTable);

					if (!$method) {
						$minParams = $maxParams = 0;
					} else {
						if ($method->getAccessLevel() == "private" && (!$inside || strcasecmp($inside->namespacedName, $name) != 0)) {
							$this->emitError($fileName, $node, self::TYPE_SCOPE_ERROR, "Attempt to call private constructor outside of class $name");
							return;
						}
						$maxParams = count($method->getParameters());
						$minParams = $method->getMinimumRequiredParameters();
						if (strcasecmp("imagick", $name) == 0) {
							$minParams = 0;
							$maxParams = 1;
						}
					}

					$passedArgCount = count($node->args);
					if ($passedArgCount < $minParams) {
						$this->emitError($fileName, $node, self::TYPE_SIGNATURE_COUNT, "Call to $name::__construct passing $passedArgCount count, required count=$minParams");
					}
					if ($passedArgCount > $maxParams) {
						//$this->emitError($fileName, $node, "Parameter mismatch","Call to $name::__construct passing too many parameters ($passedArgCount instead of $maxParams)");
					}
				}
			}
		}
	}
}
