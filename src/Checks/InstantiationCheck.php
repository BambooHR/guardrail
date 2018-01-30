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
class InstantiationCheck extends MethodCall {

	/**
	 * getCheckNodeTypes
	 * @override
	 *
	 * @return array
	 */
	public function getCheckNodeTypes() {
		return [New_::class];
	}

	/**
	 * run
	 *
	 * @override
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
				$staticNew = false;
				if ($inside) {
					if (strcasecmp($name, "self") == 0) {
						$name = strval($inside->namespacedName);
					} else if (strcasecmp($name, "static") == 0) {
						$name = strval($inside->namespacedName);
						$staticNew = true;
					}
				}
				if ($name && !$this->symbolTable->ignoreType($name)) {
					$class = $this->symbolTable->getAbstractedClass($name);
					if (!$class) {
						$this->emitError($fileName, $node, ErrorConstants::TYPE_UNKNOWN_CLASS, "Attempt to instantiate unknown class $name");
						return;
					}
					if (!$staticNew && ($class->isDeclaredAbstract() || $class->isInterface())) {
						$this->emitError($fileName, $node, ErrorConstants::TYPE_SIGNATURE_TYPE, "Attempt to instantiate abstract class $name");
						return;
					}

					$this->checkDateTimeWithoutTimeZone($fileName, $node, $name);

					$method = Util::findAbstractedMethod($name, "__construct", $this->symbolTable);

					$passedArgCount = count($node->args);
					if (!$method) {
						if ($passedArgCount > 0) {
							$this->emitError($fileName, $node, "Parameter mismatch", "Call to default constructor $name::__construct passing too many parameters");
						}
					} else {
						if ($method->getAccessLevel() == "private" && (!$inside || strcasecmp($inside->namespacedName, $name) != 0)) {
							$this->emitError($fileName, $node, self::TYPE_SCOPE_ERROR, "Attempt to call private constructor outside of class $name");
							return;
						}
						$this->checkMethod($fileName, $node, $name, "__construct", $scope, $method, $inside);
					}
				}
			}
		}
	}

	/**
	 * @param string $fileName  The file being scanned
	 * @param New_   $node      The instantiation AST node
	 * @param string $className The name of the class being instantiated.
	 * @return void
	 */
	protected function checkDateTimeWithoutTimeZone($fileName, New_ $node, $className) {

		if (
			(strcasecmp($className, "datetime") == 0 || strcasecmp($className, "datetimeimmutable") == 0) &&
			count($node->args) < 2
		) {
			$this->emitError($fileName, $node, ErrorConstants::TYPE_UNSAFE_TIME_ZONE, "Instantiating a DateTime or DateTimeImmutable without a timezone uses local time.");
		}
	}
}
