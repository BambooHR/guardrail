<?php

namespace BambooHR\Guardrail\Checks;

/**
 * Class ErrorConstants
 *
 * @package BambooHR\Guardrail\Checks
 */
class ErrorConstants {


	const TYPE_ASSIGN_MISMATCH = 'Standard.Assign.Type';
	const TYPE_ASSIGN_MISMATCH_SCALAR = 'Standard.Assign.ScalarType';
	const TYPE_ACCESS_VIOLATION = 'Standard.Access.Violation';
	const TYPE_AUTOLOAD_ERROR = 'Standard.Autoload.Unsafe';
	const TYPE_BREAK_NUMBER = 'Standard.Switch.BreakMultiple';
	const TYPE_CLASS_STORED_VARIABLE = 'Standard.Class.StoredAsVariable';
	const TYPE_CONDITIONAL_ASSIGNMENT = "Standard.ConditionalAssignment";
	const TYPE_DEBUG = 'Standard.Debug';
	const TYPE_PSR4 = "Standard.Psr4";
	const TYPE_DEPRECATED_INTERNAL = 'Standard.Deprecated.Internal';
	const TYPE_DEPRECATED_USER = 'Standard.Deprecated.User';
	const TYPE_DOC_BLOCK_MISMATCH = 'Standard.DocBlock.Mismatch';
	const TYPE_DOC_BLOCK_PARAM = 'Standard.DocBlock.Param';
	const TYPE_DOC_BLOCK_RETURN = 'Standard.DocBlock.Return';
	const TYPE_DOC_BLOCK_TYPE = 'Standard.DocBlock.Type';
	const TYPE_DOC_BLOCK_VAR = 'Standard.DocBlock.Variable';
	const TYPE_EXCEPTION_BASE = 'Standard.Exception.Base';

	const TYPE_EXCEPTION_DUPLICATE_VARIABLE = "Standard.Exception.DuplicateVariable";
	const TYPE_FUNCTION_INSIDE_FUNCTION = 'Standard.Function.InsideFunction';
	const TYPE_GLOBAL_EXPRESSION_ACCESSED = 'Standard.Global.Expression';
	const TYPE_GLOBAL_STRING_ACCESSED = 'Standard.Global.String';
	const TYPE_GOTO = 'Standard.Goto';
	const TYPE_READONLY_DECLARATION = "Standard.Incorrect.ReadOnly";
	const TYPE_ILLEGAL_ENUM = 'Standard.Illegal.Enum';
	const TYPE_INCORRECT_DYNAMIC_CALL = 'Standard.Incorrect.Dynamic';
	const TYPE_INCORRECT_STATIC_CALL = 'Standard.Incorrect.Static';
	const TYPE_INCORRECT_REGEX = 'Standard.Incorrect.Regex';
	const TYPE_METRICS_COMPLEXITY = 'Standard.Metrics.Complexity';
	const TYPE_METRICS_LINES_OF_CODE = 'Standard.Metrics.Lines';
	const TYPE_METRICS_DEPRECATED_FUNCTIONS = 'Standard.Metrics.Deprecated';
	const TYPE_MISSING_BREAK = 'Standard.Switch.Break';
	const TYPE_MISSING_CONSTRUCT = 'Standard.Constructor.MissingCall';
	const TYPE_NULL_DEREFERENCE = "Standard.Null.Dereference";

	const TYPE_NULL_METHOD_CALL = "Standard.Null.MethodCall";
	const TYPE_PARSE_ERROR = 'Standard.Parse.Error';
	const TYPE_SCOPE_ERROR = 'Standard.Scope';
	const TYPE_SPLAT_MISMATCH = "Standard.Splat.Type";
	const TYPE_SECURITY_BACKTICK = 'Standard.Security.Backtick';
	const TYPE_SECURITY_DANGEROUS = 'Standard.Security.Shell';
	const TYPE_SIGNATURE_COUNT = 'Standard.Param.Count';
	const TYPE_SIGNATURE_COUNT_EXCESS = 'Standard.Param.Count.Excess';
	const TYPE_SIGNATURE_RETURN = 'Standard.Return.Type';
	const TYPE_SIGNATURE_TYPE = 'Standard.Param.Type';
	const TYPE_SIGNATURE_NAME = 'Standard.Param.Name';
	const TYPE_CONST_TYPE = 'Standard.Const.Type';
	const TYPE_SIGNATURE_TYPE_NULL = "Standard.Null.Param";
	const TYPE_UNIMPLEMENTED_METHOD = 'Standard.Inheritance.Unimplemented';
	const TYPE_UNKNOWN_CLASS = 'Standard.Unknown.Class';
	const TYPE_OVERRIDE_BASE_CLASS = 'Standard.Override.Base';

	const TYPE_UNDOCUMENTED_EXCEPTION = 'Standard.Undocumented.Exception';

	const TYPE_UNKNOWN_CLASS_CONSTANT = 'Standard.Unknown.Class.Constant';
	const TYPE_UNKNOWN_FUNCTION = 'Standard.Unknown.Function';
	const TYPE_UNKNOWN_GLOBAL_CONSTANT = 'Standard.Unknown.Global.Constant';
	const TYPE_UNKNOWN_METHOD = 'Standard.Unknown.Class.Method';
	const TYPE_UNKNOWN_METHOD_STRING = "Standard.Unknown.Class.MethodString";
	const TYPE_UNKNOWN_CALLABLE = "Standard.Unknown.Callable";
	const TYPE_UNKNOWN_PROPERTY = 'Standard.Unknown.Property';
	const TYPE_UNKNOWN_VARIABLE = 'Standard.Unknown.Variable';
	const TYPE_UNREACHABLE_CODE = 'Standard.Unreachable';
	const TYPE_UNSAFE_TIME_ZONE = "Standard.Unsafe.TimeZone";
	const TYPE_UNSAFE_IMAGICK = "Standard.Unsafe.Imagick";
	const TYPE_UNSAFE_SUPERGLOBAL = "Standard.Unsafe.Superglobal";
	const TYPE_UNUSED_VARIABLE = 'Standard.Unused.Variable';
	const TYPE_UNUSED_PROPERTY = 'Standard.Unused.Property';
	const TYPE_USE_CASE_SENSITIVE = 'Standard.Use.CaseSensitive';
	const TYPE_VARIABLE_FUNCTION_NAME = 'Standard.VariableFunctionCall';
	const TYPE_VARIABLE_VARIABLE = 'Standard.VariableVariable';
	const TYPE_COUNTABLE_EMPTINESS_CHECK = 'Standard.Countable.Emptiness';
	const TYPE_OPEN_API_ATTRIBUTE_DOCUMENTATION_CHECK = 'Standard.OpenApiAttribute.Documentation';
	const TYPE_OPEN_API_ATTRIBUTE_DOCUMENTATION_TEAM_CHECK = 'Standard.OpenApiAttribute.Documentation.TeamName';
	const TYPE_SERVICE_METHOD_DOCUMENTATION_CHECK = 'Standard.ServiceMethod.Documentation';


	/**
	 * @return string[]
	 */
	static function getConstants() {
		$selfReflection = new \ReflectionClass(self::class);
		$constants = $selfReflection->getConstants();
		sort($constants);
		return array_values($constants);
	}
}
