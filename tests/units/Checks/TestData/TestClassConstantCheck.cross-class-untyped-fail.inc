<?php declare(strict_types=1);

class DirectionConstants {
	public const FORWARD = 1.5;
}

class PayrollDateValidator {
	public const DEFAULT_DIRECTION = DirectionConstants::FORWARD;

	public function validateParams(string $direction): void {
	}
}

$validator = new PayrollDateValidator();
$validator->validateParams(PayrollDateValidator::DEFAULT_DIRECTION);
$validator->validateParams(DirectionConstants::FORWARD);
