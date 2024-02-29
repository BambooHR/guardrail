<?php

class MyCountable implements Countable {
	public function count(): int {
		return 0;
	}

	public function isEmpty(): bool {
		return true;
	}
}

class Test {
	function foo() {
		$emptyCountable = new MyCountable();

		$emptyCountable->isEmpty();
	}
}
