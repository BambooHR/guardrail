<?php

interface parentClass {
	public function method1(): bool;
	public function method2(): mixed;
	public function method3();
	public function method4(): string;
}
class childClass implements parentClass {
	/** This should not work because int is different than bool */
	public function method1(): int {
		return true;
	}
	public function method2(): string {
		return "";
	}
	public function method3(): int {
		return 1;
	}
	public function method4(): string {
		return "1";
	}
}