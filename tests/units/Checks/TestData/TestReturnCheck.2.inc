<?php

class TestClass {
	function method(): \Generator {
		// Should fail because it returns an array, not a generator
        return [1,2,3];
	}
}