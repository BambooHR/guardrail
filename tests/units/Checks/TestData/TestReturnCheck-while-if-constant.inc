<?php declare(strict_types=1);

function testWhileTrueOneReturnInside(): int {
	while(true) {
		return 42;
	}
}

function testWhileFalseOneReturnOutside(): int {
	while(false):
		echo("test");
	endwhile;
	return 42;
}


function testOldWhileTrueOneReturnInside(): int {
	while(true):
		return 42;
	endwhile;
}

function testWhileFalseOneReturnOutside(): int {
	while(false) {
		echo("test");
	}
	return 42;
}

function testDoWhileTrueOneReturnInside(): int {
	do {
		return 42;
	} while (true);
}

function testDoWhileFalseOneReturnInside(): int {
	do {
		echo("test");
		return 42;
	} while (false);
}

function testDoWhileFalseOneReturnOutside(): int {
	do {
		echo("test");
	} while (false);
	return 42;
}

function testIfTrueOneReturnInside(): int {
	if(true) {
		return 42;
	}
}

function testIfFalseOneReturnOutside(): int {
	if(false) {
		echo("test");
	}
	return 42;
}

class TestClass {
	function testWhileTrueOneReturnInside(): int {
		while(true) {
			return 42;
		}
	}

	function testWhileFalseOneReturnOutside(): int {
		while(false) {
			echo("test");
		}
		return 42;
	}

	function testWhileFalseOneReturnOutside(): int {
		while(false):
			echo("test");
		endwhile;
		return 42;
	}


	function testOldWhileTrueOneReturnInside(): int {
		while(true):
			return 42;
		endwhile;
	}

	function testDoWhileTrueOneReturnInside(): int {
		do {
			return 42;
		} while (true);
	}

	function testDoWhileFalseOneReturnInside(): int {
		do {
			echo("test");
			return 42;
		} while (false);
	}

	function testDoWhileFalseOneReturnOutside(): int {
		do {
			echo("test");
		} while (false);
		return 42;
	}

	function testIfTrueOneReturnInside(): int {
		if(true) {
			return 42;
		}
	}

	function testIfFalseOneReturnOutside(): int {
		if(false) {
			echo("test");
		}
		return 42;
	}
}