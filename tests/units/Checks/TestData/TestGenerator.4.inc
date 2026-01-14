<?php

class TestClass {
	function method(): none {
		// Should pass because it is an empty function, which returns `none` and not a generator
	}

	function method(): void {
		// Should fail because it is an empty function, which returns `void` and not a generator
	}
}