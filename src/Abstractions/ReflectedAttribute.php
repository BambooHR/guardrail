<?php

namespace BambooHR\Guardrail\Abstractions;

use BambooHR\Guardrail\Util;
use PhpParser\Node\Arg;
use PhpParser\Node\Identifier;
use ReflectionAttribute;

class ReflectedAttribute implements AttributeInterface {
	public function __construct(private ReflectionAttribute $attribute) {}

	public function getClassName(): string {
		return $this->attribute->getName();
	}

	public function getArguments(): array {
		$args = [];
		foreach ($this->attribute->getArguments() as $name => $value) {
			$args[] = new Arg(
				value: Util::valueToExpression($value),
				name: is_string($name) ? new Identifier($name) : null
			);
		}
		return $args;
	}
}