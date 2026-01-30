<?php

namespace BambooHR\Guardrail\Abstractions;

use PhpParser\Node\Attribute as NodeAttribute;

readonly class AttributeAbstraction implements AttributeInterface {
	public function __construct(private NodeAttribute $attr) {}

	public function getClassName(): string {
		return $this->attr->name->toString();
	}

	public function getArguments(): array {
		return $this->attr->args;
	}
}
