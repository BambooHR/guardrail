<?php

namespace BambooHR\Guardrail\Abstractions;

use PhpParser\Node\Arg;

interface AttributeInterface {
	public function getClassName(): string;

	/**
	 * @return Arg[]
	 */
	public function getArguments(): array;
}
