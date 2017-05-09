<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\Checks;

use BambooHR\Guardrail\Checks\BaseCheck;
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
			if(array_key_exists($toLower, self::$dangerous)) {
				$this->emitError($fileName, $node, self::TYPE_SECURITY_DANGEROUS, "Call to dangerous function $name()");
			}

			$minimumArgs = $this->getMinimumParams($name);
			if($minimumArgs<0) {
				$this->emitError($fileName,$node,self::TYPE_UNKNOWN_FUNCTION, "Call to unknown function $name");
			}
			if(count($node->args)<$minimumArgs) {
				$this->emitError($fileName,$node,self::TYPE_SIGNATURE_COUNT, "Function call parameter count mismatch to function $name (passed ".count($node->args)." requires $minimumArgs)");
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