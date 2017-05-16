<?php

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

namespace BambooHR\Guardrail\Checks;

use N98\JUnitXml;
use PhpParser\Node;
use BambooHR\Guardrail\Scope;
use BambooHR\Guardrail\SymbolTable\SymbolTable;
use BambooHR\Guardrail\Output\OutputInterface;

abstract class BaseCheck {
	const TYPE_AUTOLOAD_ERROR ="Standard.Autoload.Unsafe";

	const TYPE_VARIABLE_VARIABLE      = "Standard.VariableVariable";
	const TYPE_VARIABLE_FUNCTION_NAME = "Standard.VariableFunctionCall";
	const TYPE_EVAL                   = "Standard.Security.Eval";

	const TYPE_SECURITY_BACKTICK="Standard.Security.Backtick";
	const TYPE_SECURITY_DANGEROUS="Standard.Security.Shell";

	const TYPE_DEPRECATED_INTERNAL="Standard.Deprecated.Internal";
	const TYPE_DEPRECATED_USER="Standard.Deprecated.User";

	const TYPE_UNKNOWN_CLASS="Standard.Unknown.Class";
	const TYPE_UNKNOWN_CLASS_CONSTANT="Standard.Unknown.Class.Constant";
	const TYPE_UNKNOWN_GLOBAL_CONSTANT="Standard.Unknown.Global.Constant";
	const TYPE_UNKNOWN_METHOD="Standard.Unknown.Class.Method";
	const TYPE_UNKNOWN_FUNCTION="Standard.Unknown.Function";
	const TYPE_UNKNOWN_VARIABLE="Standard.Unknown.Variable";
	const TYPE_UNKNOWN_PROPERTY="Standard.Unknown.Property";

	const TYPE_UNIMPLEMENTED_METHOD="Standard.Inheritance.Unimplemented";

	const TYPE_INCORRECT_STATIC_CALL="Standard.Incorrect.Static";
	const TYPE_INCORRECT_DYNAMIC_CALL="Standard.Incorrect.Dynamic";

	const TYPE_SCOPE_ERROR="Standard.Scope";
	const TYPE_SIGNATURE_COUNT="Standard.Param.Count";
	const TYPE_SIGNATURE_COUNT_EXCESS="Standard.Param.Count.Excess";
	const TYPE_SIGNATURE_TYPE="Standard.Param.Type";
	const TYPE_SIGNATURE_RETURN="Standard.Return.Type";

	const TYPE_MISSING_BREAK="Standard.Switch.Break";
	const TYPE_BREAK_NUMBER="Standard.Switch.BreakMultiple";
	const TYPE_PARSE_ERROR="Standard.Parse.Error";

	const TYPE_ACCESS_VIOLATION="Standard.Access.Violation";

	const TYPE_MISSING_CONSTRUCT="Standard.Constructor.MissingCall";

	const TYPE_GOTO = "Standard.Goto";

	/** @var SymbolTable */
	protected $symbolTable;

	/** @var \BambooHR\Guardrail\Output\OutputInterface  */
	private $doc;

	function __construct(SymbolTable $symbolTable, OutputInterface $doc) {
		$this->symbolTable=$symbolTable;
		$this->doc=$doc;
	}

	function emitError($file, \PhpParser\Node $node, $class, $message="") {
		return $this->emitErrorOnLine($file, $node->getLine(), $class, $message);
	}

	function emitErrorOnLine($file, $lineNumber, $class, $message="") {
		return $this->doc->emitError(get_class($this), $file, $lineNumber, $class, $message);
	}

	function incTests() {
		$this->doc->incTests();
	}

	/**
	 * @return string[]
	 */
	abstract function getCheckNodeTypes();

	abstract function run($fileName, $node, Node\Stmt\ClassLike $inside=null, Scope $scope=null);
}