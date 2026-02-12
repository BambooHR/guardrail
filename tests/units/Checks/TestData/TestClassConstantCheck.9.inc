<?php

class TestClass {
	const MY_CONST = 'value';
	
	public function test() {
		// Using ::class is always valid and should not trigger unknown constant error
		$className = self::class;
		return $className;
	}
}
