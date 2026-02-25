<?php declare(strict_types=1);

function testWhileTrueNoReturnInside(): int {
	while(true) {
		echo("test");
	}
}

function testWhileFalseOneReturnOutside(): int {
	while(false) {
		return 42;
	}
}

function testOldWhileTrueNoReturnInside(): int {
	while(true):
		echo("test");
	endwhile;
}

function testOldWhileFalseOneReturnOutside(): int {
	while(false):
		return 42;
	endwhile;
}

function testIfTrueNoReturnInside(): int {
	if(true) {
		echo("test");
	}
}

function testIfFalseOneReturnOutside(): int {
	if(false) {
		return 42;
	}
}

class TestClass {

	function testWhileTrueNoReturnInside(): int {
		while(true) {
			echo("test");
		}
	}

	function testWhileFalseOneReturnOutside(): int {
		while(false) {
			return 42;
		}
	}

	function testOldWhileTrueNoReturnInside(): int {
		while(true):
			echo("test");
		endwhile;
	}

	function testOldWhileFalseOneReturnOutside(): int {
		while(false):
			return 42;
		endwhile;
	}


	function testIfTrueNoReturnInside(): int {
		if(true) {
			echo("test");
		}
	}

	function testIfFalseOneReturnOutside(): int {
		if(false) {
			return 42;
		}
	}
}