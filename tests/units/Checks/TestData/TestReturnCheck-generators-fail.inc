<?php declare(strict_types=1);

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

function noYieldWithReturn(): \Generator {
	return [1,2,3];
}

function emptyWithComment(): \Generator {
	// Should fail
}

function trulyEmpty(): \Generator {}