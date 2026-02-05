<?php declare(strict_types=1);

class TestClass {
	private const string PRIVATE_TEST_STRING = 'test';
	private const int PRIVATE_TEST_INT = 1;
	private const array PRIVATE_TEST_ARRAY = [self::PRIVATE_TEST_STRING, 'test2'];
	private const array PRIVATE_TEST_WITH_SPREAD = [null, ...self::PRIVATE_TEST_ARRAY];

	public const string PUBLIC_TEST_STRING = 'test';
	public const int PUBLIC_TEST_INT = 1;
	public const array PUBLIC_TEST_ARRAY = [self::PUBLIC_TEST_STRING, 'test2'];
	public const array PUBLIC_TEST_WITH_SPREAD = [null, ...self::PUBLIC_TEST_ARRAY];

	// 4 errors for public method params, 4 in method call with params, 4 in method call with class constants
	public function publicMethod(string $a, int $b, array $c, array $d) : int {
		$this->privateMethod($d, $c, $b, $a);
		$this->privateMethod(self::PRIVATE_TEST_WITH_SPREAD, self::PRIVATE_TEST_ARRAY, self::PRIVATE_TEST_INT, self::PRIVATE_TEST_STRING);
		return 1;
	}

	private function privateMethod(string $a, int $b, array $c, array $d) : int {
		return 1;
	}
}

$test = new TestClass();
$test->publicMethod(TestClass::PUBLIC_TEST_WITH_SPREAD, TestClass::PUBLIC_TEST_ARRAY, TestClass::PUBLIC_TEST_INT, TestClass::PUBLIC_TEST_STRING);
