<?php declare(strict_types=1);

class testClass {
	function objectReturnString(): object {
		return "not an instance of object";
	}
}

function objectReturnString(): object {
	return "not an instance of object";
}