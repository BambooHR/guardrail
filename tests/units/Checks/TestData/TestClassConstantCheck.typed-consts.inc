<?php declare(strict_types=1);

class TestClass {
	private const string PRIVATE_TEST_STRING = 'test';
	private const array PRIVATE_TEST_ARRAY = [self::PRIVATE_TEST_STRING, 'test2'];
	private const array PRIVATE_TEST_WITH_SPREAD = [null, ...self::PRIVATE_TEST_ARRAY];

	public const string PUBLIC_TEST_STRING = 'test';
	public const array PUBLIC_TEST_ARRAY = [self::PUBLIC_TEST_STRING, 'test2'];
	public const array PUBLIC_TEST_WITH_SPREAD = [null, ...self::PUBLIC_TEST_ARRAY];

	public function publicMethod(string $a, array $b, array $c) : int {
		$this->privateMethod($a, $b, $c);
		$this->privateMethod(self::PRIVATE_TEST_STRING, self::PRIVATE_TEST_ARRAY, self::PRIVATE_TEST_WITH_SPREAD);
		return 1;
	}

	private function privateMethod(string $a, array $b, array $c) : int {
		return 1;
	}
}

$test = new TestClass();
$test->publicMethod(self::PUBLIC_TEST_STRING, self::PUBLIC_TEST_ARRAY, self::PUBLIC_TEST_WITH_SPREAD);
