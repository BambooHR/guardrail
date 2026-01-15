<?php

// Valid generator with yield
class GeneratorValid {
	function withYield(): \Generator {
        if (false) {
            yield 1;
        }
        return [1,2,3];
	}

	function withYieldFrom(): \Generator {
		yield from [1, 2, 3];
		return [4, 5, 6];
	}
}

// Invalid generators - missing yield
class GeneratorInvalid {
	function noYieldWithReturn(): \Generator {
        return [1,2,3];
	}

	function emptyWithComment(): \Generator {
		// Should fail
	}

	function trulyEmpty(): \Generator {}
}

// Interface and abstract - should pass
interface TestInterface {
	function method(): \Generator;
}

abstract class AbstractClass {
	abstract function method(): \Generator;
}
