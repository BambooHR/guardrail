<?php

class TestClass {
	public function test() {
		// Using mixed type should be ignored by the symbol table
		$mixed = mixed::SOME_CONST;
		return $mixed;
	}
}
