<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Checks\BaseCheck;
use BambooHR\Guardrail\TypeInferrer;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassLike;
use BambooHR\Guardrail\Scope;

class FunctionCallCheck extends BaseCheck
{
	function getCheckNodeTypes() {
		return [\PhpParser\Node\Expr\FuncCall::class];
	}

	static private $dangerous = ["exec"=>true,"shell_exec"=>true, "proc_open"=>true, "passthru"=>true, "popen"=>true, "system"=>true];
	/**
	 * @param string                        $fileName
	 * @param \PhpParser\Node\Expr\FuncCall $node
	 */
	function run($fileName, $node, ClassLike $inside=null, Scope $scope=null) {

		if ($node->name instanceof Name) {
			$name = $node->name->toString();

			$toLower = strtolower($name);
			$this->incTests();
			if (array_key_exists($toLower, self::$dangerous)) {
				$this->emitError($fileName, $node, self::TYPE_SECURITY_DANGEROUS, "Call to dangerous function $name()");
			}
			if ($toLower == "eval") {
				$this->emitError($fileName, $node, self::TYPE_EVAL, "Call to dangerous function eval()");
			}
			if ($toLower == "create_function") {
				$this->emitError($fileName, $node, self::TYPE_EVAL, "Call to dangerous function create_function()");
			}

			$func = $this->symbolTable->getAbstractedFunction($name);
			if ($func) {
				$minimumArgs = $func->getMinimumRequiredParameters($name);
				if(count($node->args)<$minimumArgs) {
					$this->emitError($fileName,$node,self::TYPE_SIGNATURE_COUNT, "Function call parameter count mismatch to function $name (passed ".count($node->args)." requires $minimumArgs)");
				}

				if($func->isDeprecated()) {
					$errorType = $func->isInternal() ? self::TYPE_DEPRECATED_INTERNAL : self::TYPE_DEPRECATED_USER;
					$this->emitError($fileName,$node, $errorType, "Call to deprecated function $name" );
				}
			}  else {
				$this->emitError($fileName,$node,self::TYPE_UNKNOWN_FUNCTION, "Call to unknown function $name");
			}
		} else {
			$inferer =new TypeInferrer($this->symbolTable);
			$type = $inferer->inferType( $inside, $node->name, $scope);
			// If it isn't known to be "callable" or "closure" then it may just be a string.
			if(strcasecmp($type,"callable")!=0 && strcasecmp($type,"closure")!=0 ) {
				$this->emitError($fileName, $node, self::TYPE_VARIABLE_FUNCTION_NAME, "Variable function name detected");
			}
		}
	}

	function getMinimumParams($name) {
		$ob = $this->symbolTable->getAbstractedFunction($name);
		if($ob) {
			return $ob->getMinimumRequiredParameters();
		} else {
			return -1;
		}
	}
}