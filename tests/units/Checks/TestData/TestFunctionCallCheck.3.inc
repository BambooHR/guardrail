<?php

print_r('Something');

debug_print_backtrace();

debug_backtrace();

debug_zval_dump($variable);

class SomeClass {

	public function testThisToo() {
		foreach ($loops as $loop) {
			print_r($loop);
		}
	}

	public function anotherCheck() {
		echo 'Something wicked this way comes.';
		debug_print_backtrace();
	}
}