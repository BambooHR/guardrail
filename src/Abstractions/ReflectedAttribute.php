<?php

namespace BambooHR\Guardrail\Abstractions;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\DNumber;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use ReflectionAttribute;

readonly class ReflectedAttribute implements AttributeInterface
{
	public function __construct(private ReflectionAttribute $reflectionAttribute) {}

	public function getName(): string {
		return $this->reflectionAttribute->getName();
	}

	public function getArgumentExpressions(): array {
		return array_map(
			fn(mixed $argumentValue) => $this->toExpression($argumentValue),
			$this->reflectionAttribute->getArguments()
		);
	}

	private function toExpression(mixed $argumentValue): ?Expr {
		return match (gettype($argumentValue)) {
			"boolean" => new ConstFetch(new Name($argumentValue ? "true" : "false")),
			"integer" => new LNumber($argumentValue),
			"double" => new DNumber($argumentValue),
			"string" => new String_($argumentValue),
			"array" => new Array_(array_map(
				fn(mixed $arrayItemValue) => new Expr\ArrayItem($this->toExpression($arrayItemValue)),
				$argumentValue
			)),
			"NULL" => new ConstFetch(new Name("null")),
			default => throw new \InvalidArgumentException("Unsupported type in attribute argument: " . gettype($argumentValue))
		};
	}
}