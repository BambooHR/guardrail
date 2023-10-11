<?php

interface parentClass {
	public function method1(int $param1, string $param2): bool;
	public function method2($param1, array $param2): string;
}
class childClass implements parentClass {
	public function method1($param1, $param2): bool {
		return true;
	}
	public function method2(mixed $param1, mixed $param2): mixed {
		return;
	}
}