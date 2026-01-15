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

// Interface and abstract - should pass
interface TestInterface {
	function method(): \Generator;
}

abstract class AbstractClass {
	abstract function method(): \Generator;
}
