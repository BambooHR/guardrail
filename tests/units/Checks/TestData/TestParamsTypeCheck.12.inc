<?php declare(strict_types=1);

// Test case for cross-class constant references being mistyped as array
class DirectionConstants {
	public const string FORWARD = 'forward';
	public const string BACKWARD = 'backward';
}

class MyValidator {
	// This constant references another class's constant but has NO type hint
	// This might cause the type inference to fail and default to array
	public const DEFAULT_DIRECTION = DirectionConstants::FORWARD;
	
	public function validateDirection(string $direction): void {
		// Method implementation
	}
}

// This should NOT produce type errors
// But if the constant is mistyped as array, it will fail
$validator = new MyValidator();
$validator->validateDirection(MyValidator::DEFAULT_DIRECTION);
