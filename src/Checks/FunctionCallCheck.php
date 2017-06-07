<?php namespace BambooHR\Guardrail\Checks;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\TypeInferrer;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassLike;
use BambooHR\Guardrail\Scope;

class FunctionCallCheck extends BaseCheck {

	/**
	 * getCheckNodeTypes
	 *
	 * @return array
	 */
	public function getCheckNodeTypes() {
		return [FuncCall::class, Node\Expr\Eval_::class];
	}

	/**
	 * @var array
	 */
	static private $dangerous = [
		'exec' => true,
		'shell_exec' => true,
		'proc_open' => true,
		'passthru' => true,
		'popen' => true,
		'system' => true,
		'create_function' => true,
	];

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

		if ($node instanceof Node\Expr\Eval_) {
			$this->emitError($fileName, $node, ErrorConstants::TYPE_SECURITY_DANGEROUS, "Call to dangerous function eval()");
		} elseif ($node->name instanceof Name) {
			$name = $node->name->toString();

			$toLower = strtolower($name);
			$this->incTests();
			if (array_key_exists($toLower, self::$dangerous)) {
				$this->emitError($fileName, $node, ErrorConstants::TYPE_SECURITY_DANGEROUS, "Call to dangerous function $name()");
			}

			$func = $this->symbolTable->getAbstractedFunction($name);
			if ($func) {
				$minimumArgs = $func->getMinimumRequiredParameters($name);
				if (count($node->args) < $minimumArgs) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_SIGNATURE_COUNT, "Function call parameter count mismatch to function $name (passed " . count($node->args) . " requires $minimumArgs)");
				}
				if ($func->isDeprecated()) {
					$errorType = $func->isInternal() ? ErrorConstants::TYPE_DEPRECATED_INTERNAL : ErrorConstants::TYPE_DEPRECATED_USER;
					$this->emitError($fileName, $node, $errorType, "Call to deprecated function $name" );
				}
			} else {
				$this->emitError($fileName, $node, ErrorConstants::TYPE_UNKNOWN_FUNCTION, "Call to unknown function $name");
			}
		} else {
			$inferer = new TypeInferrer($this->symbolTable);
			$type = $inferer->inferType( $inside, $node->name, $scope);
			// If it isn't known to be "callable" or "closure" then it may just be a string.
			if (strcasecmp($type, "callable") != 0 && strcasecmp($type, "closure") != 0 ) {
				$this->emitError($fileName, $node, ErrorConstants::TYPE_VARIABLE_FUNCTION_NAME, "Variable function name detected");
			}
		}
	}

	/**
	 * getMinimumParams
	 *
	 * @param string $name The name
	 *
	 * @return int
	 */
	public function getMinimumParams($name) {
		$ob = $this->symbolTable->getAbstractedFunction($name);
		if ($ob) {
			return $ob->getMinimumRequiredParameters();
		} else {
			return -1;
		}
	}
}