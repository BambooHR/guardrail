<?php

// Valid void/never usage
class VoidNeverValid {
	function voidWithEmptyReturn(): void {
		return;
	}

	function voidWithNoReturn(): void {
		// No return statement
	}
}
