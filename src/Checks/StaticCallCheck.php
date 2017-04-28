<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\Checks;

use PhpParser\Node\Param;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\Util;

class StaticCallCheck extends BaseCheck
{
	function getCheckNodeTypes() {
		return [\PhpParser\Node\Expr\StaticCall::class];
	}

	/**
	 * @param $fileName
	 * @param \PhpParser\Node\Expr\StaticCall $call
	 */
	function run($fileName, $call, ClassLike $inside=null, Scope $scope = null) {
		if ($call->class instanceof Name && gettype($call->name)=="string") {

			$name = $call->class->toString();
			if ($this->symbolTable->ignoreType($name)) {
				return;
			}
			$originalName=$name;
			$possibleDynamic = false;

			switch(strtolower($name)) {
				case 'self':
					$possibleDynamic = true;
					// Fall through
				case 'static':
					if(!$inside) {
						$this->emitError($fileName, $call, self::TYPE_SCOPE_ERROR, "Can't access using self:: outside of a class");
						return;
					}
					$name = $inside->namespacedName;
					break;
				case 'parent':
					if(!$inside) {
						$this->emitError($fileName, $call, self::TYPE_SCOPE_ERROR, "Can't access using parent:: outside of a class");
						return;
					}
					$possibleDynamic=true;
					if ($inside->extends) {
						$name = strval($inside->extends);
					} else {
						$this->emitError($fileName, $call, self::TYPE_SCOPE_ERROR, "Can't access using parent:: in a class with no parent");
						return;
					}
					break;
				default:
					if($inside) {
						$currentClass = strval($inside->namespacedName);
						if($this->symbolTable->isParentClassOrInterface($name, $currentClass)) {
							$possibleDynamic=true;
						}
					}
					break;
			}

			$this->incTests();
			$class = $this->symbolTable->getAbstractedClass($name);
			if (!$class) {
				if (!$this->symbolTable->ignoreType($name)) {
					$this->emitError($fileName,$call,self::TYPE_UNKNOWN_CLASS, "Static call to unknown class $name::" . $call->name);
				}
			} else {

				$method = Util::findAbstractedMethod($name, $call->name, $this->symbolTable );
				if($call->name=="__construct" && !$method) {
					// Find a PHP 4 style constructor (function name == class name)
					$method = Util::findAbstractedMethod($name, $name, $this->symbolTable);
				}

				if(!$method) {
					if(!Util::findAbstractedMethod($name, "__callStatic", $this->symbolTable) &&
						(!$possibleDynamic || !Util::findAbstractedMethod($name,"__call", $this->symbolTable))
					) {
						$this->emitError($fileName, $call,self::TYPE_UNKNOWN_METHOD, "Unable to find method.  $name::" . $call->name);
					}
				} else {
					if(!$method->isStatic()) {
						if(!$scope->isStatic() && $possibleDynamic) {
							if($call->name!="__construct" && $call->class!="parent") {
								// echo "Static call in $fileName " . $call->getLine() . "\n";
							}
						} else {
							$this->emitError($fileName, $call, self::TYPE_INCORRECT_DYNAMIC_CALL, "Attempt to call non-static method: $name::" . $call->name . " statically");
						}
					}
					$minimumParams=$method->getMinimumRequiredParameters();
					if(count($call->args)<$minimumParams) {
						$this->emitError($fileName,$call,self::TYPE_SIGNATURE_COUNT, "Static call to method $name::".$call->name." does not pass enough parameters (".count($call->args)." passed $minimumParams required)");
					}
				}
			}
		}
	}
}