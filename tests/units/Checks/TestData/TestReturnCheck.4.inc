<?php

class TestClass {
	function method(): none {
		// Should pass because it is an empty function, which returns `none` and not a generator
	}

	function method(): void {
		// Should pass because it is an empty function, which has no return
	}
}