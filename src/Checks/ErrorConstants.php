<?php namespace BambooHR\Guardrail\Checks;

/**
 * Class ErrorConstants
 *
 * @package BambooHR\Guardrail\Checks
 */
class ErrorConstants {

	const TYPE_ACCESS_VIOLATION = 'Standard.Access.Violation';
	const TYPE_AUTOLOAD_ERROR = 'Standard.Autoload.Unsafe';
	const TYPE_BREAK_NUMBER = 'Standard.Switch.BreakMultiple';
	const TYPE_DEPRECATED_INTERNAL = 'Standard.Deprecated.Internal';
	const TYPE_DEPRECATED_USER = 'Standard.Deprecated.User';
	const TYPE_DOC_BLOCK_MISMATCH = 'Standard.DocBlock.Mismatch';
	const TYPE_DOC_BLOCK_PARAM = 'Standard.DocBlock.Param';
	const TYPE_DOC_BLOCK_RETURN = 'Standard.DocBlock.Return';
	const TYPE_DOC_BLOCK_TYPE = 'Standard.DocBlock.Type';
	const TYPE_DOC_BLOCK_VAR = 'Standard.DocBlock.Variable';
	const TYPE_EVAL = 'Standard.Security.Eval';
	const TYPE_EXCEPTION_BASE = 'Standard.Exception.Base';
	const TYPE_GLOBAL_EXPRESSION_ACCESSED = 'Standard.Global.Expression';
	const TYPE_GLOBAL_STRING_ACCESSED = 'Standard.Global.String';
	const TYPE_GOTO = 'Standard.Goto';
	const TYPE_INCORRECT_DYNAMIC_CALL = 'Standard.Incorrect.Dynamic';
	const TYPE_INCORRECT_STATIC_CALL = 'Standard.Incorrect.Static';
	const TYPE_MISSING_BREAK = 'Standard.Switch.Break';
	const TYPE_MISSING_CONSTRUCT = 'Standard.Constructor.MissingCall';
	const TYPE_PARSE_ERROR = 'Standard.Parse.Error';
	const TYPE_SCOPE_ERROR = 'Standard.Scope';
	const TYPE_SECURITY_BACKTICK = 'Standard.Security.Backtick';
	const TYPE_SECURITY_DANGEROUS = 'Standard.Security.Shell';
	const TYPE_SIGNATURE_COUNT = 'Standard.Param.Count';
	const TYPE_SIGNATURE_COUNT_EXCESS = 'Standard.Param.Count.Excess';
	const TYPE_SIGNATURE_RETURN = 'Standard.Return.Type';
	const TYPE_SIGNATURE_TYPE = 'Standard.Param.Type';
	const TYPE_UNIMPLEMENTED_METHOD = 'Standard.Inheritance.Unimplemented';
	const TYPE_UNKNOWN_CLASS = 'Standard.Unknown.Class';
	const TYPE_UNKNOWN_CLASS_CONSTANT = 'Standard.Unknown.Class.Constant';
	const TYPE_UNKNOWN_FUNCTION = 'Standard.Unknown.Function';
	const TYPE_UNKNOWN_GLOBAL_CONSTANT = 'Standard.Unknown.Global.Constant';
	const TYPE_UNKNOWN_METHOD = 'Standard.Unknown.Class.Method';
	const TYPE_UNKNOWN_PROPERTY = 'Standard.Unknown.Property';
	const TYPE_UNKNOWN_VARIABLE = 'Standard.Unknown.Variable';
	const TYPE_UNUSED_VARIABLE = 'Standard.Unused.Variable';
	const TYPE_VARIABLE_FUNCTION_NAME = 'Standard.VariableFunctionCall';
	const TYPE_VARIABLE_VARIABLE = 'Standard.VariableVariable';

	/**
	 * @return string[]
	 */
	static function getConstants() {
		$ret = [];
		$selfReflection = new \ReflectionClass(self::class);
		$constants = $selfReflection->getConstants();
		sort($constants);
		foreach ($constants as $name=>$value) {
			$ret[] = $value;
		}
		return $ret;
	}
}