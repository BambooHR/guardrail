<?php

class TestClass {
	function method(): int {
		// Should fail because it is an empty function, which returns `int` and not a generator
	}
}