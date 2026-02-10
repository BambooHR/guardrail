<?php declare(strict_types=1);

class testClass {
	function stringReturnNoReturnEmpty(): string {}

	function stringReturnNoReturn(): string {
		$test = 1;
	}

	function noReturn(): string {
		$test = 1;
	}

	function noReturnNullable(): ?string {
		$test = 1;
	}
}


function stringReturnNoReturnEmpty(): string {}

function stringReturnNoReturn(): string {
	$test = 1;
}

function noReturn(): string {
	$test = 1;
}

function noReturnNullable(): ?string {
	$test = 1;
}