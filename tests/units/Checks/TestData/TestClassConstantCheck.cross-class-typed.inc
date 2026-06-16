<?php declare(strict_types=1);

// Test case for cross-class constant references
class Constants {
	public const string DIRECTION_FORWARD = 'forward';
	public const string DIRECTION_BACKWARD = 'backward';
	public const int MAX_COUNT = 100;
}

class MyClass {
	// These constants reference another class's constants
	public const string MY_DIRECTION = Constants::DIRECTION_FORWARD;
	public const int MY_MAX = Constants::MAX_COUNT;
	
	public function testMethod(string $direction, int $count): void {
		// This should work without errors
	}
}

// This should not produce type errors - the constants should be typed as string and int, not array
$obj = new MyClass();
$obj->testMethod(MyClass::MY_DIRECTION, MyClass::MY_MAX);
$obj->testMethod(Constants::DIRECTION_FORWARD, Constants::MAX_COUNT);
