<?php

interface parentClass {
	public function method1(): bool;
	public function method2(): mixed;
	public function method3();
	public function method4(): ?string;
	public function method5(): string|int|null;
	public function method6(): string|int|null;
	public function method7(): ?int;
	public function method8(): string|int|null;
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
	public function method5(): string {
		return "1";
	}
	public function method6(): int|null {
		return null;
	}
	public function method7(): ?int {
		return null;
	}
	/** This should not work because bool is not one of the parent types */
	public function method8(): ?bool {
		return null;
	}
}