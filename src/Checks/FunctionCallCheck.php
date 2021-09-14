<?php namespace BambooHR\Guardrail\Checks;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Output\OutputInterface;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\TypeInferrer;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassLike;
use BambooHR\Guardrail\Scope;
use PhpParser\Node\UnionType;

class FunctionCallCheck extends CallCheck {

	/**
	 * FunctionCallCheck constructor.
	 * @param SymbolTable     $symbolTable -
	 * @param OutputInterface $doc         -
	 */
	public function __construct(SymbolTable $symbolTable, OutputInterface $doc) {
		parent::__construct($symbolTable, $doc);
		$this->callableCheck = new CallableCheck($symbolTable, $doc);
		$this->inferenceEngine = new TypeInferrer($symbolTable);
	}


	/**
	 * getCheckNodeTypes
	 *
	 * @return array
	 */
	public function getCheckNodeTypes(): array {
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
	 * @var array
	 */
	static private $debug = [
		'print_r' => true,
		'debug_print_backtrace' => true,
		'debug_backtrace' => true,
		'debug_zval_dump' => true,
	];

	/**
	 * run
	 *
	 * @param string         $fileName The name of the file we are parsing
	 * @param Node           $node Instance of the Node
	 * @param ClassLike|null $inside Instance of the ClassLike (the class we are parsing) [optional]
	 * @param Scope|null     $scope Instance of the Scope (all variables in the current state) [optional]
	 *
	 * @return void
	 */
	public function run(string $fileName, Node $node, ClassLike $inside = null, Scope $scope = null) {

		if ($node instanceof Node\Expr\Eval_) {
			$this->emitError($fileName, $node, ErrorConstants::TYPE_SECURITY_DANGEROUS, "Call to dangerous function eval()");
		} else if ($node instanceof FuncCall) {
			if ($node->name instanceof Name) {
				$namespacedName = $node->name->hasAttribute('namespacedName') ? $node->name->getAttribute('namespacedName')->toString() : "";
				$name = $node->name->toString();

				$func = null;
				if ($namespacedName) {
					$func = $this->symbolTable->getAbstractedFunction($namespacedName);
					if ($func) {
						$name = $namespacedName;
					}
				}
				if (!$func && $namespacedName != $name) {
					$func = $this->symbolTable->getAbstractedFunction($name);
				}

				$toLower = strtolower($name);
				if (array_key_exists($toLower, self::$dangerous)) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_SECURITY_DANGEROUS, "Call to dangerous function $name()");
				}
				$this->checkForDebugMethods($fileName, $node, $name);
				$this->checkForDateWithoutTimeZone($fileName, $node, $name);
				$this->checkForRegularExpression($fileName, $node, $name);

				if ($func) {
					$minimumArgs = $func->getMinimumRequiredParameters($name);
					if (count($node->args) < $minimumArgs) {
						$this->emitError($fileName, $node, ErrorConstants::TYPE_SIGNATURE_COUNT, "Function call parameter count mismatch to function $name (passed " . count($node->args) . " requires $minimumArgs)");
					}
					if ($func->isDeprecated()) {
						$errorType = $func->isInternal() ? ErrorConstants::TYPE_DEPRECATED_INTERNAL : ErrorConstants::TYPE_DEPRECATED_USER;
						$this->emitError($fileName, $node, $errorType, "Call to deprecated function $name");
					}

					$params = $func->getParameters();
					foreach ($node->args as $index => $arg) {
						$this->checkParam($fileName, $node, $name, $scope, $inside, $arg, $index, $params);
					}
				} else {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_UNKNOWN_FUNCTION, "Call to unknown function $name");
				}
			} else {
				$inferer = new TypeInferrer($this->symbolTable);
				list($type) = $inferer->inferType($inside, $node->name, $scope);
				// If it isn't known to be "callable" or "closure" then it may just be a string.
				if (strcasecmp($type, "callable") != 0 && strcasecmp($type, "closure") != 0) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_VARIABLE_FUNCTION_NAME, "Variable ($type) function name detected");
				}
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

	/**
	 * checkForDebugMethods
	 *
	 * @param string $fileName The name of the file
	 * @param Node   $node     Instance of the Node
	 * @param string $name     The name of the function
	 *
	 * @return void
	 */
	protected function checkForDebugMethods($fileName, Node $node, $name) {
		$toLower = strtolower($name);
		if (array_key_exists($toLower, self::$debug)) {
			$this->emitError($fileName, $node, ErrorConstants::TYPE_DEBUG, "Call to common debug function $name() detected");
		}
	}

	/**
	 * @param string   $fileName The file being scanned
	 * @param FuncCall $node     The AST node
	 * @param string   $name     The name of the function being called
	 * @return void
	 */
	protected function checkForDateWithoutTimeZone($fileName, FuncCall $node, $name) {
		// Safe code does not depend on .ini settings.  If you use date(), you are tied to the local time zone.
		if (strcasecmp($name, "date") == 0) {
			$this->emitError($fileName, $node, ErrorConstants::TYPE_UNSAFE_TIME_ZONE, "The date() function always uses the local time zone.");
		}

		if (
			(strcasecmp($name, "date_create") == 0 || strcasecmp($name, "date_create_immutable") == 0) &&
			count($node->args) < 2
		) {
			$this->emitError($fileName, $node, ErrorConstants::TYPE_UNSAFE_TIME_ZONE, "Calling the date_create() function without a timezone uses the local time zone.");
		}
	}

	/**
	 * @param string   $fileName The file being scanned.
	 * @param FuncCall $node     The FuncCall node being inspected
	 * @param string   $name     The function being called.
	 *
	 * @return void
	 */
	protected function checkForRegularExpression($fileName, FuncCall $node, $name) {
		$name = strtolower($name);
		// All of these functions accept a regex in parameter 1.
		if ($name == "preg_match" || $name == "preg_match_all" || $name == "preg_replace" || $name == "preg_filter" || $name == "preg_replace_callback" || $name == "preg_filter") {
			if (count($node->args) > 0) {
				$arg = $node->args[0]->value;
				if ($arg instanceof Node\Scalar\String_ && @preg_match($arg->value, null) === false) {
					$this->emitError($fileName, $node, ErrorConstants::TYPE_INCORRECT_REGEX, "Regular expression syntax error in \"" . $arg->value . "\"");
				}
			}
		}
	}
}