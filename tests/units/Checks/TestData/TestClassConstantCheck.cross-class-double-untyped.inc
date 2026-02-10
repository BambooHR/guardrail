<?php declare(strict_types=1);

// Test case that mimics the actual bug scenario
class DirectionConstants {
	public const FORWARD = 'forward';
	public const BACKWARD = 'backward';
}

class PayrollDateValidator {
	// Constant without explicit type hint referencing another class's typed constant
	public const DEFAULT_DIRECTION = DirectionConstants::FORWARD;

	public function validateParams(string $direction): void {
		// Validation logic
	}
}

// This should NOT produce "passed array" error
$validator = new PayrollDateValidator();
$validator->validateParams(PayrollDateValidator::DEFAULT_DIRECTION);
$validator->validateParams(DirectionConstants::FORWARD);
