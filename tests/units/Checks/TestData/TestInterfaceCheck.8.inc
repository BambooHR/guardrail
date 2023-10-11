<?php

interface parentClass {
	public function method1(mixed $param1, mixed $param2);
	public function method2($param1, $param2);
}
class childClass implements parentClass {
	public function method1($param1, $param2) {
		return;
	}
	public function method2(mixed $param1, mixed $param2) {
		return;
	}
}