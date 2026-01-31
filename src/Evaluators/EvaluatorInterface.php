<?php

namespace BambooHR\Guardrail\Evaluators;

interface EvaluatorInterface
{
	function getInstanceType(): array|string;
}
