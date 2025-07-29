<?php

namespace BambooHR\Guardrail\Abstractions;

use PhpParser\Node\Arg;
use PhpParser\Node\Attribute as NodeAttribute;

readonly class AttributeAbstraction implements AttributeInterface
{
	public function __construct(
		private NodeAttribute $attribute
	) {}

	function getName(): string
	{
		return $this->attribute->name;
	}

	function getArgumentExpressions(): array
	{
		// TODO(shayman@bamboohr.com): Double check logic
		// focusing on argument order
		return array_map(
			fn(Arg $arg) => $arg->value,
			$this->attribute->args
		);
	}
}