<?php

namespace BambooHR\Guardrail\Abstractions;

use PhpParser\Node\Expr;

/**
 * This interface represents the application of an attribute.
 * It is not the attribute class itself, but rather a representation of
 * how an attribute is applied to a class, method, property, etc.
 */
interface AttributeInterface
{
	/**
	 * This method returns the name of the attribute class being applied.
	 */
	function getName(): string;

	/**
	 * This method returns an array of arguments that were passed to the
	 * attribute's constructor when it was applied.
	 *
	 * @return Expr[]
	 */
	function getArgumentExpressions(): array;
}