<?php

class TestClass {
	function method(): \Generator {
        if (false) {
            // Impossible to get here, but this enables you to return an array.
            yield 1;
        }
        return [1,2,3];
	}
}