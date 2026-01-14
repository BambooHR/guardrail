<?php

class TestClass {
	function method(): \Generator {
		// Should fail because it is an empty function, which returns `none` and not a generator
	}
}