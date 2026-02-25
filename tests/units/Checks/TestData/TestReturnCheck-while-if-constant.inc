<?php declare(strict_types=1);

function testWhileTrueOneReturnInside(): int {
	while(true) {
		return 42;
	}
}

function testWhileTrueOneReturnOutside(): int {
	while(false) {
		echo("test");
	}
	return 42;
}

function testIfTrueOneReturnInside(): int {
	if(true) {
		return 42;
	}
}

function testIfTrueOneReturnOutside(): int {
	if(false) {
		echo("test");
	}
	return 42;
}