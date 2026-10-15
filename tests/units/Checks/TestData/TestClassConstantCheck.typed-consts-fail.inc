<?php declare(strict_types=1);

class TestClass {
	public const string PUBLIC_TEST_STRING = 'test';
	public const array PUBLIC_TEST_ARRAY = [self::PUBLIC_TEST_STRING, 'test2'];
	public const array PUBLIC_TEST_WITH_SPREAD = [null, ...self::PUBLIC_TEST_ARRAY];

	public function publicMethod(string $a, array $b, array $c) : int {
		return 1;
	}
}

$test = new TestClass();
// self:: is only valid inside a class; each fetch here is a scope error.
$test->publicMethod(self::PUBLIC_TEST_STRING, self::PUBLIC_TEST_ARRAY, self::PUBLIC_TEST_WITH_SPREAD);
