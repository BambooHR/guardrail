<?php

class MyCountable implements Countable {
	public function count(): int {
		return 0;
	}
}

class Test {
	function foo() {
		$emptyCountable = new MyCountable();

		empty($emptyCountable);
	}
}
