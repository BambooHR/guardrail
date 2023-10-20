<?php

interface ParentClass {
	public function method1(): int;
}
class ChildClass implements parentClass {
	public function method1(): int {
		return true;
	}
}

class TestClass {
	public function test(?iterable $iterable) {
		return;
	}
}

(new TestClass())->test([]);