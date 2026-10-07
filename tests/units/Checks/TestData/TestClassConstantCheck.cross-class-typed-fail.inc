<?php declare(strict_types=1);

class Constants {
	public const DIRECTION = 1.5;
	public const ITEMS = [1];
}

class MyClass {
	public const MY_DIRECTION = Constants::DIRECTION;
	public const MY_ITEMS = Constants::ITEMS;

	public function testMethod(int $count, string $label): void {
	}
}

$obj = new MyClass();
$obj->testMethod(MyClass::MY_DIRECTION, "ok");
$obj->testMethod(1, MyClass::MY_ITEMS);
$obj->testMethod(Constants::DIRECTION, Constants::ITEMS);
